<?php

namespace App\Pricing;

/**
 * Pixelforge's rates and token rules (GET /v1/pricing), in integers.
 */
final readonly class PricingRules
{
    /** The text token variance is kept in parts per million so it stays an integer. */
    public const VARIANCE_SCALE = 1_000_000;

    /**
     * @param  array<string, array<string, int>>  $outputTokens  quality => size => tokens
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  quality => range
     */
    public function __construct(
        public Rates $rates,
        public int $charactersPerTextToken,
        public int $textTokenVariancePpm,
        public int $tokensPerReferenceImage,
        public array $outputTokens,
        public array $reasoningTokens,
    ) {}

    /** @param  array<string, mixed>  $pricing  the body of GET /v1/pricing */
    public static function fromPixelforge(array $pricing): self
    {
        $tokens = $pricing['tokens'];

        return new self(
            Rates::fromUsdPerMillionTokens($pricing['rates']),
            (int) $tokens['characters_per_text_token'],
            Decimal::scaled($tokens['text_token_variance'], 6),
            (int) $tokens['per_reference_image'],
            array_map(fn (array $sizes) => array_map(intval(...), $sizes), $tokens['output']),
            array_map(fn (array $range) => ['min' => (int) $range['min'], 'max' => (int) $range['max']], $tokens['reasoning']),
        );
    }

    /**
     * The shape the prompt bar's estimate reads (resources/js/lib/pricing.ts).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rates' => $this->rates->toArray(),
            'characters_per_text_token' => $this->charactersPerTextToken,
            'text_token_variance_ppm' => $this->textTokenVariancePpm,
            'tokens_per_reference_image' => $this->tokensPerReferenceImage,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
