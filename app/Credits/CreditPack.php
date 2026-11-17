<?php

namespace App\Credits;

/** A fixed amount of credits bought with one click (a fake purchase). Backed by whole dollars. */
enum CreditPack: int
{
    case Five = 5;
    case Ten = 10;
    case TwentyFive = 25;

    public function nanodollars(): int
    {
        return $this->value * 1_000_000_000;
    }
}
