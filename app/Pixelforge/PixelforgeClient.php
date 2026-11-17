<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP wrapper over Pixelforge's API (https://pixelforge.promptedit.dev/docs).
 */
class PixelforgeClient
{
    public function __construct(private readonly string $baseUrl) {}

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
}
