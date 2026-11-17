<?php

namespace App\Pricing;

use App\Pixelforge\PixelforgeClient;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The current pricing rules, read from Pixelforge's pricing endpoint and cached briefly.
 *
 * If Pixelforge can't be reached, the last pricing it gave us is used; with none at all,
 * there is nothing to estimate from and current() returns null.
 */
class PricingRulesSource
{
    private const FRESH_KEY = 'pixelforge.pricing.fresh';

    private const LAST_KNOWN_KEY = 'pixelforge.pricing.last_known';

    private const FRESH_FOR_SECONDS = 300;

    public function __construct(
        private readonly PixelforgeClient $pixelforge,
        private readonly Cache $cache,
    ) {}

    public function current(): ?PricingRules
    {
        /** @var array<string, mixed>|null $pricing */
        $pricing = $this->cache->get(self::FRESH_KEY) ?? $this->fetch() ?? $this->cache->get(self::LAST_KNOWN_KEY);

        return $pricing === null ? null : PricingRules::fromPixelforge($pricing);
    }

    /** @return array<string, mixed>|null */
    private function fetch(): ?array
    {
        try {
            $pricing = $this->pixelforge->pricing();
            PricingRules::fromPixelforge($pricing); // refuse a body we can't read before caching it
        } catch (Throwable $e) {
            Log::warning('Could not fetch Pixelforge pricing', ['error' => $e->getMessage()]);

            return null;
        }

        $this->cache->put(self::FRESH_KEY, $pricing, self::FRESH_FOR_SECONDS);
        $this->cache->forever(self::LAST_KNOWN_KEY, $pricing);

        return $pricing;
    }
}
