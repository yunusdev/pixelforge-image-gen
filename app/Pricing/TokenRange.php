<?php

namespace App\Pricing;

/**
 * One line of the itemised receipt: a token range and its provider cost range (nanodollars).
 */
final readonly class TokenRange
{
    public function __construct(
        public int $tokensLow,
        public int $tokensHigh,
        public int $providerCostLow,
        public int $providerCostHigh,
    ) {}
}
