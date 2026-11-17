<?php

use App\Generations\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Runs one attempt of the submit job, as a queue worker would, and returns it for release assertions. */
function attemptSubmit(Generation $generation): SubmitGeneration
{
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

function fakeCreateReplies(array $replies): void
{
    $sequence = Http::sequence();
    foreach ($replies as $reply) {
        $sequence->pushResponse($reply);
    }
    Http::fake(['pixelforge.test/generations' => $sequence]);
}

function accepted(Generation $generation, array $headers = []): mixed
{
    return Http::response(pixelforgeGeneration([
        'id' => 'gen_accepted',
        'metadata' => ['generation_uuid' => $generation->uuid],
        'rates' => ['input_text_tokens' => '6.25', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
    ]), 202, $headers);
}

beforeEach(function () {
    $this->freezeSecond();
    $this->generation = Generation::factory()->create();
});

test('an accepted generation moves to queued and keeps its Pixelforge id and locked rates', function () {
    fakeCreateReplies([accepted($this->generation)]);

    attemptSubmit($this->generation)->assertNotReleased();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toBe('gen_accepted')
        ->and($generation->rate_input_text_tokens)->toBe(6_250)
        ->and($generation->rate_input_image_tokens)->toBe(10_000)
        ->and($generation->rate_output_tokens)->toBe(40_000)
        ->and($generation->rate_reasoning_tokens)->toBe(12_500);
});

test('the create call sends the generation\'s own settings and UUID', function () {
    fakeCreateReplies([accepted($this->generation)]);

    attemptSubmit($this->generation);

    Http::assertSent(fn (Request $request) => $request->header('Idempotency-Key') === [$this->generation->uuid]
        && $request['prompt'] === $this->generation->prompt
        && $request['size'] === $this->generation->size
        && $request['quality'] === $this->generation->quality
        && $request['reference_images'] === $this->generation->reference_images);
});

test('at the concurrency limit it waits for a slot, retrying after Retry-After', function () {
    fakeCreateReplies([pixelforgeError('concurrency_limit_exceeded', 429, 5)]);

    attemptSubmit($this->generation)->assertReleased(delay: 5);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Waiting);
});

test('at the concurrency limit it keeps waiting until 10 minutes after submission, then fails for free as too busy', function () {
    fakeCreateReplies(array_fill(0, 3, pixelforgeError('concurrency_limit_exceeded', 429, 5)));

    $this->travel(9)->minutes();
    attemptSubmit($this->generation)->assertReleased(delay: 5);

    $this->travel(59)->seconds();
    attemptSubmit($this->generation)->assertReleased(delay: 5);

    $this->travel(1)->seconds();
    attemptSubmit($this->generation)->assertNotReleased();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('too_busy')
        ->and($generation->completed_at)->not->toBeNull();
});

test('concurrency-limit waits are not counted as tries', function () {
    fakeCreateReplies([
        ...array_fill(0, 6, pixelforgeError('concurrency_limit_exceeded', 429, 5)),
        accepted($this->generation),
    ]);

    foreach (range(1, 6) as $attempt) {
        attemptSubmit($this->generation)->assertReleased(delay: 5);
    }
    attemptSubmit($this->generation)->assertNotReleased();

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

test('overloaded and rate-limited replies are retried with growing waits of at least Retry-After, then give up after 5 tries', function (string $code, int $status, int $retryAfter, array $waits) {
    fakeCreateReplies(array_fill(0, 5, pixelforgeError($code, $status, $retryAfter)));

    foreach ($waits as $wait) {
        attemptSubmit($this->generation)->assertReleased(delay: $wait);
        expect($this->generation->fresh()->status)->toBe(GenerationStatus::Waiting);
    }

    attemptSubmit($this->generation)->assertNotReleased();

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->error_code->toBe('too_busy');
    Http::assertSentCount(5);
})->with([
    'overloaded' => ['overloaded', 503, 2, [2, 4, 8, 16]],
    'rate limit' => ['rate_limit_exceeded', 429, 17, [17, 34, 68, 136]],
]);

test('every retry reuses the same Idempotency-Key', function () {
    fakeCreateReplies([
        pixelforgeError('overloaded', 503, 2),
        pixelforgeError('concurrency_limit_exceeded', 429, 5),
        pixelforgeError('rate_limit_exceeded', 429, 3),
        accepted($this->generation),
    ]);

    foreach (range(1, 4) as $attempt) {
        attemptSubmit($this->generation);
    }

    $keys = Http::recorded()->map(fn (array $pair) => $pair[0]->header('Idempotency-Key')[0])->unique();
    expect($keys->all())->toBe([$this->generation->uuid]);
});

test('rejections fail the generation at once with their own reason', function (string $code, int $status) {
    fakeCreateReplies([pixelforgeError($code, $status)]);

    attemptSubmit($this->generation)->assertNotReleased();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe($code)
        ->and($generation->error_message)->toBe("Pixelforge says {$code}.");
})->with([
    'blocked prompt' => ['content_policy_violation', 400],
    'validation' => ['validation_failed', 422],
    'daily limit' => ['daily_limit_exceeded', 429],
]);

test('failed is free: every give-up and rejection leaves the generation at $0.00 with no charge recorded', function (array $replies, int $attempts) {
    fakeCreateReplies($replies);

    foreach (range(1, $attempts) as $attempt) {
        attemptSubmit($this->generation);
    }

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->charged())->toBe(0)
        ->and($generation->actual_charge)->toBeNull()
        ->and($generation->provider_cost)->toBeNull();
})->with([
    'blocked' => [fn () => [pixelforgeError('content_policy_violation', 400)], 1],
    'validation' => [fn () => [pixelforgeError('validation_failed', 422)], 1],
    'daily limit' => [fn () => [pixelforgeError('daily_limit_exceeded', 429)], 1],
    'overloaded 5 times' => [fn () => array_fill(0, 5, pixelforgeError('overloaded', 503, 2)), 5],
    'rate-limited 5 times' => [fn () => array_fill(0, 5, pixelforgeError('rate_limit_exceeded', 429, 2)), 5],
]);

test('failed is free: running out of the 10 minutes leaves the generation at $0.00', function () {
    fakeCreateReplies([pixelforgeError('concurrency_limit_exceeded', 429, 5)]);
    $this->travel(10)->minutes();

    attemptSubmit($this->generation);

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->charged()->toBe(0);
});

test('never twice: a retried create that Pixelforge replays returns the same generation, recorded once', function () {
    fakeCreateReplies([
        pixelforgeError('overloaded', 503, 2),
        accepted($this->generation, ['Idempotent-Replayed' => 'true']),
    ]);

    attemptSubmit($this->generation);
    attemptSubmit($this->generation);

    expect(Generation::count())->toBe(1)
        ->and($this->generation->fresh()->pixelforge_id)->toBe('gen_accepted');
});

test('never twice: once accepted, running the job again does not call Pixelforge', function () {
    fakeCreateReplies([accepted($this->generation)]);

    attemptSubmit($this->generation);
    attemptSubmit($this->generation)->assertNotReleased();

    Http::assertSentCount(1);
});

test('a status Pixelforge reported before the create call returned is never moved backward', function () {
    Http::fake(['pixelforge.test/generations' => function () {
        // A webhook lands while the create call is in flight (ticket 04 wires the endpoint).
        $this->generation->forceFill(['status' => GenerationStatus::Processing, 'pixelforge_id' => 'gen_accepted'])->save();

        return accepted($this->generation);
    }]);

    attemptSubmit($this->generation);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Processing);
});

test('a bad API key fails the generation for free instead of retrying', function () {
    fakeCreateReplies([pixelforgeError('invalid_api_key', 401)]);

    attemptSubmit($this->generation)->assertNotReleased();

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->charged()->toBe(0);
});
