<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * The pricing calculator: pure integer arithmetic on nanodollars (ADR-0001). No I/O.
 *
 * resources/js/lib/pricing.ts mirrors estimate() for the prompt bar; this class is
 * the source of truth for anything recorded.
 */
final class Pricing
{
    /** The markup: charge = provider cost × 125 / 100. */
    private const MARKUP_NUMERATOR = 125;

    private const MARKUP_DENOMINATOR = 100;

    public static function estimate(GenerationSettings $settings, PricingRules $rules): Estimate
    {
        $output = $rules->outputTokens[$settings->quality][$settings->size]
            ?? throw new InvalidArgumentException("Unknown quality and size: {$settings->quality} {$settings->size}");
        $reasoning = $rules->reasoningTokens[$settings->quality];
        $rates = $rules->rates;

        $textTokens = self::ceilDiv($settings->promptCharacters(), $rules->charactersPerTextToken);
        $scale = PricingRules::VARIANCE_SCALE;
        $imageTokens = $settings->referenceImageCount * $rules->tokensPerReferenceImage;

        $prompt = self::line(
            intdiv($textTokens * ($scale - $rules->textTokenVariancePpm), $scale),
            self::ceilDiv($textTokens * ($scale + $rules->textTokenVariancePpm), $scale),
            $rates->inputTextTokens,
        );
        $referenceImages = self::line($imageTokens, $imageTokens, $rates->inputImageTokens);
        $imageOutput = self::line($output, $output, $rates->outputTokens);
        $thinking = self::line($reasoning['min'], $reasoning['max'], $rates->reasoningTokens);

        $lines = [$prompt, $referenceImages, $imageOutput, $thinking];
        $providerCostLow = array_sum(array_map(fn (TokenRange $line) => $line->providerCostLow, $lines));
        $providerCostHigh = array_sum(array_map(fn (TokenRange $line) => $line->providerCostHigh, $lines));

        return new Estimate(
            low: self::chargeRoundedDown($providerCostLow),
            high: self::charge($providerCostHigh),
            providerCostLow: $providerCostLow,
            providerCostHigh: $providerCostHigh,
            prompt: $prompt,
            referenceImages: $referenceImages,
            imageOutput: $imageOutput,
            thinking: $thinking,
        );
    }

    /** Provider cost: Σ tokens × nanodollars per token. Always exact. */
    public static function providerCost(Usage $usage, Rates $rates): int
    {
        return $usage->inputTextTokens * $rates->inputTextTokens
            + $usage->inputImageTokens * $rates->inputImageTokens
            + $usage->outputTokens * $rates->outputTokens
            + $usage->reasoningTokens * $rates->reasoningTokens;
    }

    /** The charge: provider cost plus the markup, rounded up to the nanodollar. */
    public static function charge(int $providerCost): int
    {
        return self::ceilDiv($providerCost * self::MARKUP_NUMERATOR, self::MARKUP_DENOMINATOR);
    }

    /** A provider cost with the markup, rounded down (the estimate's low end). */
    public static function chargeRoundedDown(int $providerCost): int
    {
        return intdiv($providerCost * self::MARKUP_NUMERATOR, self::MARKUP_DENOMINATOR);
    }

    /** The actual charge, capped at the top of the estimate; we absorb any excess (ADR-0002). */
    public static function actualCharge(Usage $usage, Rates $rates, int $estimateHigh): ActualCharge
    {
        $providerCost = self::providerCost($usage, $rates);
        $charge = self::charge($providerCost);
        $capped = $charge > $estimateHigh;

        return new ActualCharge(
            providerCost: $providerCost,
            charge: $capped ? $estimateHigh : $charge,
            capped: $capped,
            absorbed: $capped ? $charge - $estimateHigh : 0,
        );
    }

    private static function line(int $tokensLow, int $tokensHigh, int $nanodollarsPerToken): TokenRange
    {
        return new TokenRange($tokensLow, $tokensHigh, $tokensLow * $nanodollarsPerToken, $tokensHigh * $nanodollarsPerToken);
    }

    /** Ceiling division for non-negative integers. */
    private static function ceilDiv(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }
}
