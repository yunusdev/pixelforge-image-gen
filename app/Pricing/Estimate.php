<?php

namespace App\Pricing;

/**
 * The range a generation's charge will fall within, with the receipt lines behind it.
 * All amounts in nanodollars.
 */
final readonly class Estimate
{
    public function __construct(
        public int $low,
        public int $high,
        public int $providerCostLow,
        public int $providerCostHigh,
        public TokenRange $prompt,
        public TokenRange $referenceImages,
        public TokenRange $imageOutput,
        public TokenRange $thinking,
    ) {}
}
