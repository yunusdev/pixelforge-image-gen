<?php

namespace App\Pricing;

/**
 * Token usage Pixelforge reports on a succeeded generation.
 */
final readonly class Usage
{
    public function __construct(
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
    ) {}
}
