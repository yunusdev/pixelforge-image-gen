<?php

namespace App\Models;

use App\Generations\GenerationStatus;
use App\Pricing\Rates;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request by a user for one image, from submit until it succeeds or fails.
 * Status changes go through App\Generations\GenerationLifecycle only.
 *
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property string|null $pixelforge_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property list<string> $reference_images
 * @property int $estimate_low
 * @property int $estimate_high
 * @property int $estimate_provider_cost_low
 * @property int $estimate_provider_cost_high
 * @property int|null $rate_input_text_tokens
 * @property int|null $rate_input_image_tokens
 * @property int|null $rate_output_tokens
 * @property int|null $rate_reasoning_tokens
 * @property int|null $usage_input_text_tokens
 * @property int|null $usage_input_image_tokens
 * @property int|null $usage_output_tokens
 * @property int|null $usage_reasoning_tokens
 * @property int|null $provider_cost
 * @property int|null $actual_charge
 * @property bool $capped
 * @property int $absorbed
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property int $submit_tries
 * @property string|null $last_submit_error
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'capped' => 'boolean',
            'image_url_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What the user has paid for it, in nanodollars: the actual charge once succeeded, otherwise nothing. */
    public function charged(): int
    {
        return $this->status === GenerationStatus::Succeeded ? (int) $this->actual_charge : 0;
    }

    public function rates(): ?Rates
    {
        return $this->rate_input_text_tokens === null ? null : new Rates(
            (int) $this->rate_input_text_tokens,
            (int) $this->rate_input_image_tokens,
            (int) $this->rate_output_tokens,
            (int) $this->rate_reasoning_tokens,
        );
    }

    /** @param  Builder<self>  $query */
    public function scopeUnfinished(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::unfinished());
    }
}
