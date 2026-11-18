<?php

use App\Pixelforge\PermanentError;
use App\Pixelforge\PixelforgeAuthError;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\TemporaryError;
use App\Pricing\Rates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

dataset('settings', [[[
    'prompt' => 'A lighthouse on a cliff at dawn',
    'size' => '1536x1024',
    'quality' => 'high',
    'reference_images' => ['https://example.com/a.png'],
]]]);

test('creating a generation sends exactly the documented fields, the Idempotency-Key and the metadata', function (array $settings) {
    Http::fake(['pixelforge.test/generations' => Http::response(pixelforgeGeneration(), 202)]);

    app(PixelforgeClient::class)->createGeneration('0b7d6c1e-uuid', $settings);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://pixelforge.test/generations'
        && $request->header('Authorization') === ['Bearer test-key']
        && $request->header('Idempotency-Key') === ['0b7d6c1e-uuid']
        && $request->data() === [
            'prompt' => 'A lighthouse on a cliff at dawn',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png'],
            'metadata' => ['generation_uuid' => '0b7d6c1e-uuid'],
        ]);
})->with('settings');

test('an accepted generation comes back as a snapshot with its id, status and rates in nanodollars per token', function (array $settings) {
    Http::fake(['pixelforge.test/generations' => Http::response(pixelforgeGeneration([
        'id' => 'gen_abc',
        'rates' => ['input_text_tokens' => '6.25', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'metadata' => ['generation_uuid' => 'u-1'],
    ]), 202)]);

    $snapshot = app(PixelforgeClient::class)->createGeneration('u-1', $settings);

    expect($snapshot->id)->toBe('gen_abc')
        ->and($snapshot->status)->toBe('queued')
        ->and($snapshot->generationUuid)->toBe('u-1')
        ->and($snapshot->rates)->toEqual(new Rates(6_250, 10_000, 40_000, 12_500))
        ->and($snapshot->usage)->toBeNull();
})->with('settings');

test('busy replies are temporary, with their Retry-After', function (string $code, int $status, int $retryAfter, array $settings) {
    Http::fake(['pixelforge.test/generations' => pixelforgeError($code, $status, $retryAfter)]);

    try {
        app(PixelforgeClient::class)->createGeneration('u-1', $settings);
        $this->fail('Expected a temporary error');
    } catch (TemporaryError $e) {
        expect($e->errorCode)->toBe($code)->and($e->retryAfterSeconds)->toBe($retryAfter);
    }
})->with([
    'concurrency limit' => ['concurrency_limit_exceeded', 429, 5],
    'overloaded' => ['overloaded', 503, 2],
    'rate limit' => ['rate_limit_exceeded', 429, 17],
])->with('settings');

test('rejections are permanent, with their own code', function (string $code, int $status, array $settings) {
    Http::fake(['pixelforge.test/generations' => pixelforgeError($code, $status)]);

    try {
        app(PixelforgeClient::class)->createGeneration('u-1', $settings);
        $this->fail('Expected a permanent error');
    } catch (PermanentError $e) {
        expect($e->errorCode)->toBe($code);
    }
})->with([
    'blocked prompt' => ['content_policy_violation', 400],
    'validation' => ['validation_failed', 422],
    'daily limit' => ['daily_limit_exceeded', 429],
    'idempotency key reused' => ['idempotency_key_reused', 409],
])->with('settings');

test('a bad API key is an auth error', function (array $settings) {
    Http::fake(['pixelforge.test/generations' => pixelforgeError('invalid_api_key', 401)]);

    app(PixelforgeClient::class)->createGeneration('u-1', $settings);
})->with('settings')->throws(PixelforgeAuthError::class);

test('an unexpected server error is temporary, so it is retried under the same key', function (array $settings) {
    Http::fake(['pixelforge.test/generations' => Http::response('Bad gateway', 502)]);

    try {
        app(PixelforgeClient::class)->createGeneration('u-1', $settings);
        $this->fail('Expected a temporary error');
    } catch (TemporaryError $e) {
        expect($e->retryAfterSeconds)->toBeGreaterThan(0);
    }
})->with('settings');

test('retrieving a generation returns its snapshot, including usage once succeeded', function () {
    Http::fake(['pixelforge.test/generations/gen_abc' => Http::response(pixelforgeGeneration([
        'id' => 'gen_abc',
        'status' => 'succeeded',
        'usage' => ['input_text_tokens' => 9, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 640, 'total_tokens' => 1749],
        'image_url' => 'https://img.pixelforge.test/gen_abc.png',
        'image_url_expires_at' => '2026-11-19T10:01:00Z',
        'completed_at' => '2026-11-18T10:01:00Z',
    ]))]);

    $snapshot = app(PixelforgeClient::class)->generation('gen_abc');

    expect($snapshot->status)->toBe('succeeded')
        ->and($snapshot->usage->outputTokens)->toBe(1100)
        ->and($snapshot->usage->reasoningTokens)->toBe(640)
        ->and($snapshot->imageUrl)->toBe('https://img.pixelforge.test/gen_abc.png');
});

test('usage asks for the UTC date range and returns each day\'s cost in nanodollars', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([
        '2026-11-01' => '0.169080000',
        '2026-11-02' => '0.000000000',
    ]))]);

    $usage = app(PixelforgeClient::class)->usage(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-02'));

    expect($usage)->toBe(['2026-11-01' => 169_080_000, '2026-11-02' => 0]);
    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://pixelforge.test/usage?start_date=2026-11-01&end_date=2026-11-02'
        && $request->header('Authorization') === ['Bearer test-key']);
});

test('exact cost: usage read as a JSON number keeps every nanodollar, even a tiny one', function () {
    $reply = pixelforgeUsage(['2026-11-01' => '0', '2026-11-02' => '0']);
    $reply['data'][0]['cost_usd'] = 0.00001;
    $reply['data'][1]['cost_usd'] = 0.169080001;
    Http::fake(['pixelforge.test/usage*' => Http::response($reply)]);

    $usage = app(PixelforgeClient::class)->usage(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-02'));

    expect($usage)->toBe(['2026-11-01' => 10_000, '2026-11-02' => 169_080_001]);
});

test('usage failures come back as typed errors', function () {
    Http::fake(['pixelforge.test/usage*' => pixelforgeError('overloaded', 503, 2)]);

    app(PixelforgeClient::class)->usage(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-02'));
})->throws(TemporaryError::class);
