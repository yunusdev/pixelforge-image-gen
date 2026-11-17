<?php

namespace App\Generations;

use App\Models\Generation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The status chips above the feed: All, Running, Done, Failed. */
enum FeedFilter: string
{
    case All = 'all';
    case Running = 'running';
    case Done = 'succeeded';
    case Failed = 'failed';

    /** Anything unknown or missing shows everything. */
    public static function fromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::All) : self::All;
    }

    /**
     * @param  Builder<Generation>|HasMany<Generation, *>  $query
     * @return Builder<Generation>|HasMany<Generation, *>
     */
    public function apply(Builder|HasMany $query): Builder|HasMany
    {
        return match ($this) {
            self::All => $query,
            self::Running => $query->whereIn('status', GenerationStatus::unfinished()),
            self::Done => $query->where('status', GenerationStatus::Succeeded),
            self::Failed => $query->where('status', GenerationStatus::Failed),
        };
    }
}
