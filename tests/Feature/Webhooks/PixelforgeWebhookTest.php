<?php

use App\Generations\GenerationStatus;
use App\Models\Generation;
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
