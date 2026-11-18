<?php

namespace App\Generations;

use App\Models\Generation;
use RuntimeException;

/**
 * Pixelforge reported a success without the usage or rates needed to price it. Nothing is recorded,
 * so the generation stays unfinished: a webhook is answered with an error and resent, and the
 * sweep asks again on its next run.
 */
final class IncompleteSuccess extends RuntimeException
{
    public static function for(Generation $generation): self
    {
        return new self("Pixelforge reported generation {$generation->uuid} succeeded without usage or rates.");
    }
}
