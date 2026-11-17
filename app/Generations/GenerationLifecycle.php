<?php

namespace App\Generations;

use App\Credits\Ledger;
use App\Models\Generation;
use App\Pixelforge\PixelforgeGeneration;
use App\Pricing\Pricing;
use Illuminate\Support\Facades\DB;

/**
 * The single place that moves a generation between statuses. The submit job, webhooks
 * (GenerationWebhooks) and the missed-webhook sweep (MissedWebhookSweep) all call apply().
 *
 * Every move happens in one transaction with the generation row locked, and only forward:
 * Waiting for a slot → queued → processing → succeeded or failed. Final statuses never change.
 */
class GenerationLifecycle
{
    public function __construct(private readonly Ledger $ledger) {}

    /**
     * Apply what Pixelforge reports about a generation. Its id and locked rates are kept the
     * first time we see them; the status moves only if the snapshot is further along.
     */
    public function apply(Generation $generation, PixelforgeGeneration $snapshot): Generation
    {
        return $this->locked($generation, function (Generation $generation) use ($snapshot): void {
            $generation->pixelforge_id ??= $snapshot->id;

            if ($generation->rate_input_text_tokens === null && $snapshot->rates !== null) {
                $generation->fill([
                    'rate_input_text_tokens' => $snapshot->rates->inputTextTokens,
                    'rate_input_image_tokens' => $snapshot->rates->inputImageTokens,
                    'rate_output_tokens' => $snapshot->rates->outputTokens,
                    'rate_reasoning_tokens' => $snapshot->rates->reasoningTokens,
                ]);
            }

            $next = GenerationStatus::fromPixelforge($snapshot->status);

            if ($generation->status->canMoveTo($next)) {
                $this->moveTo($generation, $next, $snapshot);
            }

            $generation->save();
        });
    }

    /**
     * Fail a generation Pixelforge never accepted (rejected outright, or retries ran out).
     * Nothing was created at Pixelforge, so nothing is charged.
     */
    public function failBeforeAcceptance(Generation $generation, string $errorCode, ?string $errorMessage = null): Generation
    {
        return $this->locked($generation, function (Generation $generation) use ($errorCode, $errorMessage): void {
            if (! $generation->status->canMoveTo(GenerationStatus::Failed)) {
                return;
            }

            $generation->fill([
                'status' => GenerationStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ])->save();
        });
    }

    private function moveTo(Generation $generation, GenerationStatus $next, PixelforgeGeneration $snapshot): void
    {
        $generation->status = $next;

        if ($next === GenerationStatus::Processing || $next->isFinal()) {
            $generation->started_at ??= $snapshot->startedAt;
        }

        if ($next === GenerationStatus::Failed) {
            $generation->fill([
                'error_code' => $snapshot->errorCode ?? 'internal_error',
                'error_message' => $snapshot->errorMessage,
                'completed_at' => $snapshot->completedAt ?? now(),
            ]);
        }

        if ($next === GenerationStatus::Succeeded) {
            $this->recordSuccess($generation, $snapshot);
        }
    }

    /**
     * Usage × the generation's locked rates gives the provider cost; the actual charge is capped
     * at the estimate's top (ADR-0002). The ledger entry for it is written in this same transaction.
     */
    private function recordSuccess(Generation $generation, PixelforgeGeneration $snapshot): void
    {
        $usage = $snapshot->usage;
        $rates = $generation->rates() ?? $snapshot->rates;

        $generation->fill([
            'image_url' => $snapshot->imageUrl,
            'image_url_expires_at' => $snapshot->imageUrlExpiresAt,
            'completed_at' => $snapshot->completedAt ?? now(),
        ]);

        if ($usage === null || $rates === null) {
            return;
        }

        $actual = Pricing::actualCharge($usage, $rates, $generation->estimate_high);

        $generation->fill([
            'usage_input_text_tokens' => $usage->inputTextTokens,
            'usage_input_image_tokens' => $usage->inputImageTokens,
            'usage_output_tokens' => $usage->outputTokens,
            'usage_reasoning_tokens' => $usage->reasoningTokens,
            'provider_cost' => $actual->providerCost,
            'actual_charge' => $actual->charge,
            'capped' => $actual->capped,
            'absorbed' => $actual->absorbed,
        ]);

        $this->ledger->charge($generation);
    }

    /** @param  callable(Generation): void  $change */
    private function locked(Generation $generation, callable $change): Generation
    {
        return DB::transaction(function () use ($generation, $change): Generation {
            $locked = Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();
            $change($locked);

            return $locked;
        });
    }
}
