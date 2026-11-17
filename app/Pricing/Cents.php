<?php

namespace App\Pricing;

/**
 * Nanodollars shown as dollars and cents for users ("$0.47"), as resources/js/lib/money.ts does.
 */
final class Cents
{
    private const NANODOLLARS_PER_CENT = 10_000_000;

    /** Rounded up to the cent, for amounts that are a ceiling ("up to $0.47"). Non-negative amounts only. */
    public static function up(int $nanodollars): string
    {
        $cents = intdiv($nanodollars + self::NANODOLLARS_PER_CENT - 1, self::NANODOLLARS_PER_CENT);

        return '$'.number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
