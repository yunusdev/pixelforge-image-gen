<?php

namespace App\Credits;

use App\Models\Generation;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A user's running list of credit movements; their balance is its total. Entries are only
 * ever added, and the balance cached on the user changes in the same transaction as each one.
 */
class Ledger
{
    public function signUpBonus(User $user): LedgerEntry
    {
        return $this->record($user->id, (int) config('credits.sign_up_bonus'), LedgerReason::SignUpBonus);
    }

    public function buyPack(User $user, CreditPack $pack): LedgerEntry
    {
        return $this->record($user->id, $pack->nanodollars(), LedgerReason::CreditPack);
    }

    /**
     * Take a succeeded generation's actual charge from its owner's balance. Called by the
     * generation lifecycle inside its transaction; the unique generation_id makes a second
     * charge for the same generation impossible.
     */
    public function charge(Generation $generation): ?LedgerEntry
    {
        if ($generation->actual_charge === null) {
            return null;
        }

        return $this->record($generation->user_id, -$generation->actual_charge, LedgerReason::GenerationCharge, $generation->id);
    }

    private function record(int $userId, int $amount, LedgerReason $reason, ?int $generationId = null): LedgerEntry
    {
        return DB::transaction(function () use ($userId, $amount, $reason, $generationId): LedgerEntry {
            $entry = LedgerEntry::create([
                'user_id' => $userId,
                'amount' => $amount,
                'reason' => $reason,
                'generation_id' => $generationId,
            ]);

            User::query()->whereKey($userId)->increment('balance', $amount);

            return $entry;
        });
    }
}
