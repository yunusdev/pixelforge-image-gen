<?php

namespace App\Jobs;

use App\Generations\FailureReason;
use App\Generations\GenerationLifecycle;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PermanentError;
use App\Pixelforge\PixelforgeAuthError;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\TemporaryError;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a saved generation to Pixelforge, retrying while Pixelforge is busy.
 *
 * - Concurrency limit: wait for a slot, retrying after Retry-After, until 10 minutes after submission.
 * - Overloaded, rate limit or an unexpected server error: up to 5 tries, each wait at least
 *   Retry-After and doubling.
 * - Rejections (content policy, validation, daily limit): fail at once.
 *
 * Retries are delayed releases back onto the queue, never sleeps, and reuse the generation's
 * UUID as the Idempotency-Key, so Pixelforge never creates or bills it twice. Every give-up
 * fails the generation for free.
 */
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public const SLOT_WAIT_MINUTES = 10;

    public const MAX_TRIES = 5;

    /** Unexpected exceptions (not Pixelforge replies) before the queue gives up. */
    public int $maxExceptions = 3;

    public function __construct(public Generation $generation) {}

    /** The queue's own safety net, beyond the limits handled below. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(self::SLOT_WAIT_MINUTES + 5);
    }

    public function handle(PixelforgeClient $pixelforge, GenerationLifecycle $lifecycle): void
    {
        $generation = $this->generation->fresh();

        // Already accepted (or finished, or matched by an early webhook): nothing to send.
        if ($generation === null || $generation->status !== GenerationStatus::Waiting) {
            return;
        }

        try {
            $snapshot = $pixelforge->createGeneration($generation->uuid, [
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_images,
            ]);
        } catch (TemporaryError $busy) {
            $this->retryLater($generation, $busy, $lifecycle);

            return;
        } catch (PermanentError $rejected) {
            $lifecycle->failBeforeAcceptance($generation, $rejected->errorCode, $rejected->getMessage());

            return;
        } catch (PixelforgeAuthError $auth) {
            Log::error('Pixelforge rejected our API key', ['generation' => $generation->uuid, 'error' => $auth->getMessage()]);
            $lifecycle->failBeforeAcceptance($generation, $auth->errorCode, $auth->getMessage());

            return;
        }

        $lifecycle->apply($generation, $snapshot);
    }

    /** The queue gave up (e.g. repeated unexpected exceptions): the generation still ends, for free. */
    public function failed(?Throwable $exception): void
    {
        app(GenerationLifecycle::class)->failBeforeAcceptance($this->generation, FailureReason::TOO_BUSY, $exception?->getMessage());
    }

    private function retryLater(Generation $generation, TemporaryError $busy, GenerationLifecycle $lifecycle): void
    {
        $generation->last_submit_error = $busy->errorCode;

        if (now()->greaterThanOrEqualTo($generation->created_at->addMinutes(self::SLOT_WAIT_MINUTES))) {
            $generation->save();
            $lifecycle->failBeforeAcceptance($generation, FailureReason::TOO_BUSY, $busy->getMessage());

            return;
        }

        if ($busy->isConcurrencyLimit()) {
            $generation->save();
            $this->release($busy->retryAfterSeconds);

            return;
        }

        $generation->submit_tries++;
        $generation->save();

        if ($generation->submit_tries >= self::MAX_TRIES) {
            $lifecycle->failBeforeAcceptance($generation, FailureReason::TOO_BUSY, $busy->getMessage());

            return;
        }

        $this->release($busy->retryAfterSeconds * 2 ** ($generation->submit_tries - 1));
    }
}
