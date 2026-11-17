<?php

namespace App\Generations;

use UnexpectedValueException;

/**
 * Where a generation is. It only ever moves forward:
 * Waiting for a slot → queued → processing → succeeded or failed (both final).
 */
enum GenerationStatus: string
{
    /** Saved, not yet accepted by Pixelforge (waiting for a slot or retrying after "busy"). */
    case Waiting = 'waiting';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** @return list<self> */
    public static function unfinished(): array
    {
        return [self::Waiting, self::Queued, self::Processing];
    }

    public static function fromPixelforge(string $status): self
    {
        return match ($status) {
            'queued' => self::Queued,
            'processing' => self::Processing,
            'succeeded' => self::Succeeded,
            'failed' => self::Failed,
            default => throw new UnexpectedValueException("Unknown Pixelforge status: {$status}"),
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /** True if moving to $next goes forward; final statuses never move. */
    public function canMoveTo(self $next): bool
    {
        return ! $this->isFinal() && $next->rank() > $this->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Waiting => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }
}
