<?php

namespace App\Pricing;

/**
 * Nanodollars shown as dollars and cents for users ("$0.47"), as resources/js/lib/money.ts does.
 */
final class Cents
{
    /** Rounded up to the cent, for amounts that are a ceiling ("up to $0.47"). Non-negative amounts only. */
    public static function up(int $nanodollars): string
    {
        return self::format(intdiv($nanodollars + Nanodollars::PER_CENT - 1, Nanodollars::PER_CENT));
    }

    /** Rounded down to the cent, for amounts that can be spent ("$0.50 available"). */
    public static function down(int $nanodollars): string
    {
        $cents = intdiv($nanodollars, Nanodollars::PER_CENT);

        if ($nanodollars < 0 && $nanodollars % Nanodollars::PER_CENT !== 0) {
            $cents--;
        }

        return self::format($cents);
    }

    private static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.'$'.number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
