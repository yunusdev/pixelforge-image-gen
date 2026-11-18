<?php

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
| Feature tests run against Postgres (see phpunit.xml) and reset the
| database between tests.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Pixelforge's GET /v1/pricing response, as published in its docs. */
function pixelforgePricing(array $rates = []): array
{
    return [
        'object' => 'pricing',
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'rates' => array_merge([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ], $rates),
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}

/** A Pixelforge generation object, as POST/GET /v1/generations and webhooks return it. */
function pixelforgeGeneration(array $overrides = []): array
{
    return array_merge([
        'id' => 'gen_01HXYZ',
        'object' => 'generation',
        'status' => 'queued',
        'prompt' => 'A lighthouse on a cliff at dawn',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => [],
        'metadata' => [],
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'error' => null,
        'created_at' => '2026-11-18T10:00:00Z',
        'started_at' => null,
        'completed_at' => null,
    ], $overrides);
}

/** A Pixelforge error reply, shaped as its docs describe. */
function pixelforgeError(string $code, int $status, ?int $retryAfter = null): PromiseInterface
{
    return Http::response(
        ['error' => ['type' => 'error', 'code' => $code, 'message' => "Pixelforge says {$code}.", 'param' => null]],
        $status,
        $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter],
    );
}

/**
 * Pixelforge's GET /v1/usage response for the given days, as its docs describe.
 *
 * @param  array<string, string>  $costs  UTC date => cost_usd, e.g. ['2026-11-17' => '0.169080000']
 */
function pixelforgeUsage(array $costs): array
{
    $data = array_map(fn (string $date, string $cost) => [
        'date' => $date,
        'generations' => 0,
        'input_text_tokens' => 0,
        'input_image_tokens' => 0,
        'output_tokens' => 0,
        'reasoning_tokens' => 0,
        'cost_usd' => $cost,
    ], array_keys($costs), $costs);

    return [
        'object' => 'usage',
        'start_date' => array_key_first($costs),
        'end_date' => array_key_last($costs),
        'data' => $data,
        'total' => ['generations' => 0, 'input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => '0.000000000'],
    ];
}
