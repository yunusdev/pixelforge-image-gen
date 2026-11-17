<?php

namespace App\Credits;

/** Why a ledger entry moved credits. */
enum LedgerReason: string
{
    case SignUpBonus = 'sign_up_bonus';
    case CreditPack = 'credit_pack';
    case GenerationCharge = 'generation_charge';
}
