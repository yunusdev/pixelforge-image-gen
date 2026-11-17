<?php

namespace App\Pricing;

/**
 * Pixelforge's rates as nanodollars per token, one per token type.
 *
 * Pixelforge quotes dollars per 1M tokens; nanodollars per token is that
 * figure × 1000, exact while the quote has at most 3 decimals (ADR-0001).
 */
final readonly class Rates
{
    public function __construct(
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
    ) {}

    /**
     * @param  array<string, string>  $rates  USD per 1M tokens, keyed by token type, as Pixelforge sends them
     */
    public static function fromUsdPerMillionTokens(array $rates): self
    {
        return new self(
            Decimal::scaled($rates['input_text_tokens'], 3),
            Decimal::scaled($rates['input_image_tokens'], 3),
            Decimal::scaled($rates['output_tokens'], 3),
            Decimal::scaled($rates['reasoning_tokens'], 3),
        );
    }

    /** @return array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int} */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
