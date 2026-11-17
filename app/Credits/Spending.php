<?php

namespace App\Credits;

use App\Models\LedgerEntry;
use App\Models\User;
use Carbon\CarbonImmutable;

/** What a user spent on generations per UTC day, from their ledger's charge entries. */
final class Spending
{
    /**
     * The last $days UTC days up to today, oldest first, each with what was charged (nanodollars).
     *
     * @return list<array{date: string, amount: int}>
     */
    public static function lastDays(User $user, int $days): array
    {
        $first = CarbonImmutable::now('UTC')->startOfDay()->subDays($days - 1);

        $byDay = LedgerEntry::query()
            ->whereBelongsTo($user)
            ->where('reason', LedgerReason::GenerationCharge)
            ->where('created_at', '>=', $first)
            ->selectRaw('date(created_at) as day, -sum(amount) as spent')
            ->groupBy('day')
            ->pluck('spent', 'day');

        return array_map(function (int $offset) use ($first, $byDay): array {
            $date = $first->addDays($offset)->toDateString();

            return ['date' => $date, 'amount' => (int) ($byDay[$date] ?? 0)];
        }, range(0, $days - 1));
    }
}
