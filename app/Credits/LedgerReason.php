<?php

namespace App\Credits;

/** Why a ledger entry moved credits. */
enum LedgerReason: string
{
    case SignUpBonus = 'sign_up_bonus';
    case CreditPack = 'credit_pack';
    case GenerationCharge = 'generation_charge';

    /** How a ledger line reads to the user. */
    public function label(int $amount): string
    {
        return match ($this) {
            self::SignUpBonus => 'Sign-up bonus',
            self::CreditPack => 'Bought $'.intdiv($amount, 1_000_000_000).' credit pack',
            self::GenerationCharge => 'Image charge',
        };
    }
}
