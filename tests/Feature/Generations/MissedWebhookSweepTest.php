<?php

use App\Generations\GenerationStatus;
use App\Models\Generation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const PIXELFORGE_GENERATIONS = 'https://pixelforge.test/generations/';

function stuckGeneration(string $pixelforgeId, GenerationStatus $status = GenerationStatus::Processing, int $minutesAgo = 3): Generation
{
    return Generation::factory()->create([
        'status' => $status,
        'pixelforge_id' => $pixelforgeId,
        'updated_at' => now()->subMinutes($minutesAgo),
    ]);
}

function pixelforgeSucceeded(string $id, array $rates = []): array
{
    return pixelforgeGeneration([
        'id' => $id,
        'status' => 'succeeded',
        'usage' => ['input_text_tokens' => 9, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 640, 'total_tokens' => 1749],
        'rates' => [...pixelforgeGeneration()['rates'], ...$rates],
        'image_url' => "https://img.pixelforge.test/{$id}.png",
        'image_url_expires_at' => '2026-11-19T10:01:00Z',
        'completed_at' => '2026-11-18T10:01:00Z',
    ]);
}

test('exact cost: a generation unfinished for over 2 minutes is fetched and finished with its exact charge', function () {
    Http::fake([PIXELFORGE_GENERATIONS.'gen_stuck' => Http::response(pixelforgeSucceeded('gen_stuck'))]);
    $generation = stuckGeneration('gen_stuck');

    $this->artisan('generations:check-missed')->assertSuccessful();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->provider_cost->toBe(52_045_000)
        ->actual_charge->toBe(65_056_250)
        ->image_url->toBe('https://img.pixelforge.test/gen_stuck.png');
});

test('exact cost: a success reported without usage is left unfinished to ask again, and the rest of the batch is still checked', function () {
    Http::fake([
        PIXELFORGE_GENERATIONS.'gen_bare' => Http::response([...pixelforgeSucceeded('gen_bare'), 'usage' => null]),
        PIXELFORGE_GENERATIONS.'gen_next' => Http::response(pixelforgeSucceeded('gen_next')),
    ]);
    $bare = stuckGeneration('gen_bare', minutesAgo: 5);
    $next = stuckGeneration('gen_next');

    $this->artisan('generations:check-missed')->assertSuccessful();

    expect($bare->fresh())->status->toBe(GenerationStatus::Processing)->actual_charge->toBeNull()
        ->and($next->fresh())->status->toBe(GenerationStatus::Succeeded)->actual_charge->toBe(65_056_250);
});

test('failed is free: a generation Pixelforge reports failed is finished with no charge', function () {
    Http::fake([PIXELFORGE_GENERATIONS.'gen_stuck' => Http::response(pixelforgeGeneration([
        'id' => 'gen_stuck', 'status' => 'failed',
        'error' => ['code' => 'internal_error', 'message' => 'Upstream crashed.'],
    ]))]);
    $generation = stuckGeneration('gen_stuck', GenerationStatus::Queued);

    $this->artisan('generations:check-missed')->assertSuccessful();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->error_code->toBe('internal_error')
        ->charged()->toBe(0);
});

test('never twice: the backup check and the webhook both reporting success record one charge', function () {
    Http::fake([PIXELFORGE_GENERATIONS.'gen_both' => Http::response(pixelforgeSucceeded('gen_both'))]);
    config(['services.pixelforge.webhook_secret' => 'whsec_sweep']);
    $generation = stuckGeneration('gen_both');

    $this->artisan('generations:check-missed')->assertSuccessful();

    // The webhook arrives late, with rates that would charge more if it were applied again.
    $body = json_encode(['id' => 'evt_late', 'object' => 'event', 'type' => 'generation.succeeded', 'created_at' => '2026-11-18T10:05:00Z',
        'data' => ['object' => pixelforgeSucceeded('gen_both', ['output_tokens' => '80.00'])]]);
    $t = now()->getTimestamp();
    $this->call('POST', '/webhooks/pixelforge', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$body}", 'whsec_sweep'),
    ], content: $body)->assertOk();

    $this->artisan('generations:check-missed')->assertSuccessful();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->actual_charge->toBe(65_056_250)
        ->capped->toBeFalse();
    expect($generation->chargeEntry()->count())->toBe(1)
        ->and($generation->user->fresh()->balance)->toBe(5_000_000_000 - 65_056_250);
});

test('generations updated in the last 2 minutes, final, or not yet accepted are left alone', function () {
    Http::fake();
    stuckGeneration('gen_recent', minutesAgo: 1);
    stuckGeneration('gen_done', GenerationStatus::Succeeded);
    stuckGeneration('gen_failed', GenerationStatus::Failed);
    Generation::factory()->create(['updated_at' => now()->subMinutes(5)]); // waiting, no Pixelforge id

    $this->artisan('generations:check-missed')->assertSuccessful();

    Http::assertNothingSent();
});

test('each run fetches at most 10 generations, oldest first, to respect the rate limit', function () {
    Http::fake(fn (Request $request) => Http::response(pixelforgeGeneration([
        'id' => basename($request->url()), 'status' => 'processing',
    ])));
    foreach (range(1, 12) as $i) {
        stuckGeneration("gen_{$i}", minutesAgo: 3 + $i);
    }

    $this->artisan('generations:check-missed')->assertSuccessful();

    Http::assertSentCount(10);
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/gen_1') || str_ends_with($request->url(), '/gen_2'));

    // Checked generations wait their turn again, so the two left over are fetched next run.
    $this->artisan('generations:check-missed')->assertSuccessful();
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/gen_1'));
});

test('a busy reply from Pixelforge stops the run until the next minute', function () {
    Http::fake([PIXELFORGE_GENERATIONS.'*' => pixelforgeError('rate_limit_exceeded', 429, 30)]);
    stuckGeneration('gen_a', minutesAgo: 5);
    stuckGeneration('gen_b', minutesAgo: 4);

    $this->artisan('generations:check-missed')->assertSuccessful();

    Http::assertSentCount(1);
});

test('a generation Pixelforge cannot find is skipped and the rest are still checked', function () {
    Http::fake([
        PIXELFORGE_GENERATIONS.'gen_gone' => pixelforgeError('not_found', 404),
        PIXELFORGE_GENERATIONS.'gen_ok' => Http::response(pixelforgeSucceeded('gen_ok')),
    ]);
    $gone = stuckGeneration('gen_gone', minutesAgo: 5);
    $ok = stuckGeneration('gen_ok', minutesAgo: 4);

    $this->artisan('generations:check-missed')->assertSuccessful();

    expect($gone->fresh()->status)->toBe(GenerationStatus::Processing)
        ->and($ok->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

test('the check runs every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'generations:check-missed'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});
