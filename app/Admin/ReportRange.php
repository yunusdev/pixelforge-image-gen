<?php

namespace App\Admin;

use Carbon\CarbonImmutable;

/** The Admin report's UTC days, both ends inclusive. Generations count on the day they completed. */
final readonly class ReportRange
{
    public const DEFAULT_DAYS = 30;

    /** Pixelforge's usage report covers at most 92 days. */
    public const MAX_DAYS = 92;

    private function __construct(public CarbonImmutable $from, public CarbonImmutable $to) {}

    /** The given dates, or the last 30 UTC days ending today. */
    public static function of(?string $from, ?string $to): self
    {
        $end = $to === null ? CarbonImmutable::now('UTC')->startOfDay() : CarbonImmutable::parse($to, 'UTC')->startOfDay();
        $start = $from === null ? $end->subDays(self::DEFAULT_DAYS - 1) : CarbonImmutable::parse($from, 'UTC')->startOfDay();

        return new self($start, $end);
    }

    /** The first moment after the range. */
    public function end(): CarbonImmutable
    {
        return $this->to->addDay();
    }

    /** @return list<string> Y-m-d, newest first */
    public function dates(): array
    {
        $count = (int) $this->from->diffInDays($this->to) + 1;

        return array_map(fn (int $offset) => $this->to->subDays($offset)->toDateString(), range(0, $count - 1));
    }

    public function includes(string $date): bool
    {
        return $date >= $this->from->toDateString() && $date <= $this->to->toDateString();
    }

    /** @return array{from: string, to: string, maxDays: int} */
    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'maxDays' => self::MAX_DAYS];
    }
}
