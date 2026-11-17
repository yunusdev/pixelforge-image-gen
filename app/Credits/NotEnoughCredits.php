<?php

namespace App\Credits;

use App\Pricing\Cents;
use RuntimeException;

/** The available balance can't cover the top of a generation's estimate. Amounts in nanodollars. */
final class NotEnoughCredits extends RuntimeException
{
    public function __construct(public readonly int $available, public readonly int $estimateHigh)
    {
        parent::__construct(sprintf(
            'Not enough credits. %s available, this could cost up to %s.',
            Cents::down($available),
            Cents::up($estimateHigh),
        ));
    }
}
