<?php

namespace App\Pricing;

/**
 * What a user picks in the prompt bar, as far as pricing cares.
 */
final readonly class GenerationSettings
{
    public function __construct(
        public string $prompt,
        public string $size,
        public string $quality,
        public int $referenceImageCount,
    ) {}

    /** Pixelforge counts Unicode characters, not bytes. */
    public function promptCharacters(): int
    {
        return mb_strlen($this->prompt, 'UTF-8');
    }
}
