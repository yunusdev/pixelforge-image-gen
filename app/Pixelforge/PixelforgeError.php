<?php

namespace App\Pixelforge;

use RuntimeException;

/**
 * An error reply from Pixelforge, keyed by its documented `code`.
 */
abstract class PixelforgeError extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
