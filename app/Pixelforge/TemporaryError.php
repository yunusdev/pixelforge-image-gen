<?php

namespace App\Pixelforge;

/**
 * Pixelforge is busy (concurrency limit, overloaded, rate limit, or an unexpected
 * server error). Nothing was created; the same request may be sent again later
 * under the same Idempotency-Key.
 */
final class TemporaryError extends PixelforgeError
{
    public const CONCURRENCY_LIMIT = 'concurrency_limit_exceeded';

    public function __construct(string $errorCode, string $message, public readonly int $retryAfterSeconds)
    {
        parent::__construct($errorCode, $message);
    }

    /** Pixelforge's 3-in-flight limit: wait for a slot rather than counting it as a failed try. */
    public function isConcurrencyLimit(): bool
    {
        return $this->errorCode === self::CONCURRENCY_LIMIT;
    }
}
