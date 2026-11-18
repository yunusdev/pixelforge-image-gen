<?php

namespace App\Pricing;

/** Money is integer nanodollars (ADR-0001): billionths of a dollar. */
final class Nanodollars
{
    public const PER_DOLLAR = 1_000_000_000;

    public const PER_CENT = 10_000_000;
}
