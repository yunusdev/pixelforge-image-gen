<?php

namespace App\Credits;

use App\Models\Generation;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Whether a user can start a generation (ADR-0002). Their available balance is the balance
 * minus the estimate top of each of their generations still in progress, worked out on the
 * spot rather than stored as a hold.
 */
final class Affordability
{
    /** The estimate tops of the user's unfinished generations, in nanodollars. */
    public static function setAside(User $user): int
    {
        return (int) Generation::query()->whereBelongsTo($user)->unfinished()->sum('estimate_high');
    }

    public static function availableBalance(User $user): int
    {
        return (int) User::query()->whereKey($user->getKey())->value('balance') - self::setAside($user);
    }

    /**
     * Start something costing up to $estimateHigh only if the available balance covers it.
     * The user row stays locked until $start has saved the new generation, so rapid
     * submits are checked one after another and can't overspend together.
     *
     * @template T
     *
     * @param  Closure(): T  $start
     * @return T
     *
     * @throws NotEnoughCredits
     */
    public static function startIfAffordable(User $user, int $estimateHigh, Closure $start): mixed
    {
        return DB::transaction(function () use ($user, $estimateHigh, $start) {
            $balance = (int) User::query()->whereKey($user->getKey())->lockForUpdate()->value('balance');
            $available = $balance - self::setAside($user);

            if ($estimateHigh > $available) {
                throw new NotEnoughCredits($available, $estimateHigh);
            }

            return $start();
        });
    }
}
