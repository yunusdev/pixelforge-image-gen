<?php

namespace App\Pricing;

/**
 * What a succeeded generation is charged, capped at its estimate's top (ADR-0002). Nanodollars.
 */
final readonly class ActualCharge
{
    public function __construct(
        public int $providerCost,
        public int $charge,
        public bool $capped,
        public int $absorbed,
    ) {}
}
