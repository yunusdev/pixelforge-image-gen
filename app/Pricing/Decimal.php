<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * Turns a non-negative decimal (as Pixelforge sends it) into an integer
 * scaled by 10^$places, by reading its digits rather than doing float maths.
 */
final class Decimal
{
    public static function scaled(string|int|float $value, int $places): int
    {
        // JSON numbers like 0.15 arrive as floats; PHP prints their shortest round-trip form.
        $text = is_float($value) ? self::floatToText($value) : (string) $value;

        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $text, $parts) !== 1) {
            throw new InvalidArgumentException("Not a non-negative decimal: {$text}");
        }

        $fraction = rtrim($parts[2] ?? '', '0');

        if (strlen($fraction) > $places) {
            throw new InvalidArgumentException("{$text} has more than {$places} decimal places");
        }

        return (int) ($parts[1].str_pad($fraction, $places, '0'));
    }

    private static function floatToText(float $value): string
    {
        $text = (string) $value;

        return str_contains($text, 'E')
            ? rtrim(rtrim(sprintf('%.12F', $value), '0'), '.')
            : $text;
    }
}
