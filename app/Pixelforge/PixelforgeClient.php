<?php

namespace App\Pixelforge;

use App\Pricing\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP wrapper over Pixelforge's API (https://pixelforge.promptedit.dev/docs).
 *
 * Error replies become typed outcomes: TemporaryError (try again after Retry-After),
 * PermanentError (don't), PixelforgeAuthError (our key is wrong).
 */
class PixelforgeClient
{
    /** Codes that mean "busy, nothing created": safe to send again under the same Idempotency-Key. */
    private const TEMPORARY_CODES = ['concurrency_limit_exceeded', 'overloaded', 'rate_limit_exceeded'];

    /** Used when Pixelforge doesn't say how long to wait. */
    private const DEFAULT_RETRY_AFTER_SECONDS = 2;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey = '',
    ) {}

    /**
     * GET /v1/pricing: public rates and token rules (no API key needed).
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException|RequestException
     */
    public function pricing(): array
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(5)
            ->get('pricing')
            ->throw()
            ->json();
    }

    /**
     * POST /v1/generations, with our generation UUID as the Idempotency-Key and in metadata,
     * so a retry never creates or bills a second generation.
     *
     * @param  array{prompt: string, size: string, quality: string, reference_images: array<int, string>}  $settings
     *
     * @throws TemporaryError|PermanentError|PixelforgeAuthError
     */
    public function createGeneration(string $generationUuid, array $settings): PixelforgeGeneration
    {
        $response = $this->send(fn (PendingRequest $http) => $http
            ->withHeaders(['Idempotency-Key' => $generationUuid])
            ->post('generations', [
                'prompt' => $settings['prompt'],
                'size' => $settings['size'],
                'quality' => $settings['quality'],
                'reference_images' => array_values($settings['reference_images']),
                'metadata' => [PixelforgeGeneration::UUID_METADATA_KEY => $generationUuid],
            ]));

        return PixelforgeGeneration::fromArray($response->json());
    }

    /**
     * GET /v1/generations/{id}.
     *
     * @throws TemporaryError|PermanentError|PixelforgeAuthError
     */
    public function generation(string $pixelforgeId): PixelforgeGeneration
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('generations/'.rawurlencode($pixelforgeId)));

        return PixelforgeGeneration::fromArray($response->json());
    }

    /**
     * GET /v1/usage: what Pixelforge billed this key per UTC day (by completion), both dates inclusive.
     *
     * @return array<string, int> UTC date (Y-m-d) => cost in nanodollars
     *
     * @throws TemporaryError|PermanentError|PixelforgeAuthError
     */
    public function usage(CarbonInterface $from, CarbonInterface $to): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('usage', [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ]));

        $costs = [];

        foreach ((array) $response->json('data') as $day) {
            $costs[(string) $day['date']] = Decimal::scaled($day['cost_usd'], 9);
        }

        return $costs;
    }

    /** @param  callable(PendingRequest): Response  $request */
    private function send(callable $request): Response
    {
        try {
            $response = $request(Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->asJson()
                ->withToken($this->apiKey)
                ->timeout(10));
        } catch (ConnectionException $e) {
            throw new TemporaryError('connection_failed', $e->getMessage(), self::DEFAULT_RETRY_AFTER_SECONDS);
        }

        if ($response->successful()) {
            return $response;
        }

        $code = (string) ($response->json('error.code') ?? 'http_'.$response->status());
        $message = (string) ($response->json('error.message') ?? $response->reason());

        if ($response->status() === 401) {
            throw new PixelforgeAuthError($code, $message);
        }

        if (in_array($code, self::TEMPORARY_CODES, true) || $response->serverError()) {
            $retryAfter = (int) $response->header('Retry-After');

            throw new TemporaryError($code, $message, $retryAfter > 0 ? $retryAfter : self::DEFAULT_RETRY_AFTER_SECONDS);
        }

        throw new PermanentError($code, $message);
    }
}
