<?php

use App\Generations\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

const WEBHOOK_SECRET = 'whsec_test_secret';

beforeEach(function () {
    config(['services.pixelforge.webhook_secret' => WEBHOOK_SECRET]);
});

/** A Pixelforge webhook event wrapping a generation snapshot. */
function webhookEvent(string $type, array $generation = [], string $id = 'evt_1'): array
{
    return [
        'id' => $id,
        'object' => 'event',
        'type' => $type,
        'created_at' => '2026-11-18T10:01:00Z',
        'data' => ['object' => pixelforgeGeneration($generation)],
    ];
}

/** Sends a raw body to the webhook endpoint, signed the way Pixelforge signs it unless a signature is given. */
function deliver(array|string $event, ?string $signature = null, ?int $timestamp = null): TestResponse
{
    $body = is_string($event) ? $event : json_encode($event);
    $timestamp ??= now()->getTimestamp();
    $signature ??= 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, WEBHOOK_SECRET);

    $headers = ['CONTENT_TYPE' => 'application/json'];
    if ($signature !== '') {
        $headers['HTTP_PIXELFORGE_SIGNATURE'] = $signature;
    }

    return test()->call('POST', '/webhooks/pixelforge', server: $headers, content: $body);
}

function succeededGeneration(array $overrides = []): array
{
    return [
        'status' => 'succeeded',
        'usage' => ['input_text_tokens' => 9, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 640, 'total_tokens' => 1749],
        'image_url' => 'https://img.pixelforge.test/gen_hook.png',
        'image_url_expires_at' => '2026-11-19T10:01:00Z',
        'started_at' => '2026-11-18T10:00:05Z',
        'completed_at' => '2026-11-18T10:01:00Z',
        ...$overrides,
    ];
}

test('a validly signed event is acknowledged', function () {
    deliver(webhookEvent('ping'))->assertOk();
});

test('a missing signature is rejected with 400', function () {
    deliver(webhookEvent('ping'), signature: '')->assertStatus(400);
});

test('an invalid signature is rejected with 400', function () {
    deliver(webhookEvent('ping'), signature: 't='.now()->getTimestamp().',v1='.str_repeat('a', 64))->assertStatus(400);
});

test('a signature over a different body is rejected with 400', function () {
    $timestamp = now()->getTimestamp();
    $other = json_encode(webhookEvent('ping', id: 'evt_other'));
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$other, WEBHOOK_SECRET);

    deliver(webhookEvent('ping'), signature: $signature)->assertStatus(400);
});

test('a stale signature (over 300 seconds old) is rejected with 400', function () {
    deliver(webhookEvent('ping'), timestamp: now()->subSeconds(301)->getTimestamp())->assertStatus(400);
    deliver(webhookEvent('ping', id: 'evt_2'), timestamp: now()->subSeconds(300)->getTimestamp())->assertOk();
});

test('a signature from the future beyond 300 seconds is rejected with 400', function () {
    deliver(webhookEvent('ping'), timestamp: now()->addSeconds(301)->getTimestamp())->assertStatus(400);
});

test('every event is rejected when no signing secret is configured', function () {
    config(['services.pixelforge.webhook_secret' => '']);
    $body = json_encode(webhookEvent('ping'));
    $timestamp = now()->getTimestamp();

    deliver($body, signature: 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, ''))->assertStatus(400);
});

test('the endpoint sits outside CSRF: no token or session needed', function () {
    // The test call carries no CSRF token; a web-middleware route would answer 419.
    deliver(webhookEvent('ping'))->assertOk()->assertSee('OK');
});

function acceptedGeneration(string $pixelforgeId = 'gen_hook', GenerationStatus $status = GenerationStatus::Queued): Generation
{
    return Generation::factory()->create(['status' => $status, 'pixelforge_id' => $pixelforgeId]);
}

test('a processing event moves the generation forward, matched by Pixelforge id', function () {
    $generation = acceptedGeneration();

    deliver(webhookEvent('generation.processing', ['id' => 'gen_hook', 'status' => 'processing', 'started_at' => '2026-11-18T10:00:05Z']))->assertOk();

    expect($generation->fresh()->status)->toBe(GenerationStatus::Processing);
});

test('exact cost: a succeeded event with known usage records the exact provider cost and charge', function () {
    $generation = acceptedGeneration(status: GenerationStatus::Processing);

    deliver(webhookEvent('generation.succeeded', succeededGeneration(['id' => 'gen_hook'])))->assertOk();

    // 9 text × $5 + 1100 output × $40 + 640 reasoning × $12.50 per 1M = $0.052045; +25% = $0.06505625
    $fresh = $generation->fresh();
    expect($fresh)
        ->status->toBe(GenerationStatus::Succeeded)
        ->provider_cost->toBe(52_045_000)
        ->actual_charge->toBe(65_056_250)
        ->capped->toBeFalse()
        ->image_url->toBe('https://img.pixelforge.test/gen_hook.png')
        ->and($fresh->image_url_expires_at->toIso8601ZuluString())->toBe('2026-11-19T10:01:00Z');
});

test('exact cost: a charge above the estimate top is capped at it', function () {
    $generation = acceptedGeneration();

    deliver(webhookEvent('generation.succeeded', succeededGeneration([
        'id' => 'gen_hook',
        'rates' => [...pixelforgeGeneration()['rates'], 'output_tokens' => '80.00'],
    ])))->assertOk();

    // 96,045,000 provider cost → 120,056,250 uncapped; estimate top 86,337,500
    expect($generation->fresh())
        ->actual_charge->toBe(86_337_500)
        ->capped->toBeTrue()
        ->absorbed->toBe(33_718_750);
});

test('exact cost: a succeeded event without usage is refused so Pixelforge resends it, and the resend is charged', function () {
    $generation = acceptedGeneration(status: GenerationStatus::Processing);

    deliver(webhookEvent('generation.succeeded', succeededGeneration(['id' => 'gen_hook', 'usage' => null])))->assertServerError();

    expect($generation->fresh())->status->toBe(GenerationStatus::Processing)->actual_charge->toBeNull();

    deliver(webhookEvent('generation.succeeded', succeededGeneration(['id' => 'gen_hook'])))->assertOk();

    expect($generation->fresh())->status->toBe(GenerationStatus::Succeeded)->actual_charge->toBe(65_056_250);
});

test('an event arriving before the send has returned is matched by the metadata UUID', function () {
    $generation = Generation::factory()->create();

    deliver(webhookEvent('generation.processing', [
        'id' => 'gen_early',
        'status' => 'processing',
        'metadata' => ['generation_uuid' => $generation->uuid],
    ]))->assertOk();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Processing)
        ->pixelforge_id->toBe('gen_early');
});

test('never twice: the same event delivered twice is recorded and charged once', function () {
    $generation = acceptedGeneration();
    $event = webhookEvent('generation.succeeded', succeededGeneration(['id' => 'gen_hook']), id: 'evt_dup');

    deliver($event)->assertOk();
    deliver($event)->assertOk();

    expect($generation->fresh()->actual_charge)->toBe(65_056_250)
        ->and(DB::table('processed_webhook_events')->where('event_id', 'evt_dup')->count())->toBe(1)
        ->and($generation->chargeEntry()->count())->toBe(1)
        ->and($generation->user->fresh()->balance)->toBe(5_000_000_000 - 65_056_250);
});

test('never twice: a replayed event id is a no-op even if its contents differ', function () {
    $generation = acceptedGeneration();
    $event = webhookEvent('generation.processing', ['id' => 'gen_hook', 'status' => 'processing'], id: 'evt_same');

    deliver($event)->assertOk();
    $event['data']['object'] = pixelforgeGeneration(succeededGeneration(['id' => 'gen_hook']));
    deliver($event)->assertOk();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Processing)
        ->actual_charge->toBeNull();
});

test('a late processing event after succeeded changes nothing', function () {
    $generation = acceptedGeneration();

    deliver(webhookEvent('generation.succeeded', succeededGeneration(['id' => 'gen_hook']), id: 'evt_a'))->assertOk();
    deliver(webhookEvent('generation.processing', ['id' => 'gen_hook', 'status' => 'processing'], id: 'evt_b'))->assertOk();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->actual_charge->toBe(65_056_250);
});

test('failed is free: generation_failed records no charge and its own message', function () {
    $generation = acceptedGeneration();

    deliver(webhookEvent('generation.failed', [
        'id' => 'gen_hook', 'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'Could not render.'],
    ]))->assertOk();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->actual_charge->toBeNull()
        ->charged()->toBe(0)
        ->error_code->toBe('generation_failed');
});

test('failed is free: internal_error records no charge and its own message', function () {
    $generation = acceptedGeneration();

    deliver(webhookEvent('generation.failed', [
        'id' => 'gen_hook', 'status' => 'failed',
        'error' => ['code' => 'internal_error', 'message' => 'Upstream crashed.'],
    ]))->assertOk();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->actual_charge->toBeNull()
        ->charged()->toBe(0)
        ->error_code->toBe('internal_error');
});

test('failed is free: a generation we gave up on that Pixelforge still made stays free, and its cost is recorded as absorbed', function () {
    $generation = Generation::factory()->status(GenerationStatus::Failed)->create([
        'error_code' => 'too_busy', 'pixelforge_id' => null, 'completed_at' => '2026-11-17T23:58:00Z',
    ]);
    $late = fn (string $id) => webhookEvent('generation.succeeded', succeededGeneration([
        'id' => 'gen_late', 'metadata' => ['generation_uuid' => $generation->uuid],
    ]), id: $id);

    deliver($late('evt_late'))->assertOk();
    deliver($late('evt_late_again'))->assertOk();

    // 9 text × $5 + 1100 output × $40 + 640 reasoning × $12.50 per 1M = $0.052045, billed to us once.
    $fresh = $generation->fresh();
    expect($fresh)
        ->status->toBe(GenerationStatus::Failed)
        ->error_code->toBe('too_busy')
        ->charged()->toBe(0)
        ->actual_charge->toBeNull()
        ->provider_cost->toBe(52_045_000)
        ->absorbed->toBe(52_045_000)
        ->pixelforge_id->toBe('gen_late')
        ->and($fresh->completed_at->toIso8601ZuluString())->toBe('2026-11-18T10:01:00Z')
        ->and($fresh->chargeEntry()->count())->toBe(0)
        ->and($fresh->user->fresh()->balance)->toBe(5_000_000_000);
});

test('an event for an unknown generation gets 2xx and is logged', function () {
    Log::spy();

    deliver(webhookEvent('generation.succeeded', succeededGeneration(['id' => 'gen_stranger'])))->assertOk();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'unknown generation'));
    expect(Generation::count())->toBe(0);
});

test('a ping gets 2xx and is logged', function () {
    Log::spy();

    deliver(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'created_at' => '2026-11-18T10:01:00Z', 'data' => []])->assertOk();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'ping'));
});
