<?php

namespace App\Models;

use App\Credits\LedgerReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a user's ledger. Append-only: written through App\Credits\Ledger only.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount Signed nanodollars.
 * @property LedgerReason $reason
 * @property int|null $generation_id
 * @property CarbonImmutable $created_at
 */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reason' => LedgerReason::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
