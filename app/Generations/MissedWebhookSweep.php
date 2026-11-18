<?php

namespace App\Generations;

use App\Models\Generation;
use App\Pixelforge\PermanentError;
use App\Pixelforge\PixelforgeAuthError;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\TemporaryError;
use Illuminate\Support\Facades\Log;

/**
 * The backup for webhooks that never arrive: fetches accepted generations that haven't moved for
 * over 2 minutes from Pixelforge and passes each snapshot through the same lifecycle code.
 *
 * Runs every minute and fetches at most BATCH_SIZE, oldest first, so it stays well inside
 * Pixelforge's 60 requests/minute (shared with submitting). A busy reply ends the run early.
 */
class MissedWebhookSweep
{
    public const STALE_AFTER_MINUTES = 2;

    public const BATCH_SIZE = 10;

    public function __construct(
        private readonly PixelforgeClient $pixelforge,
        private readonly GenerationLifecycle $lifecycle,
    ) {}

    /** @return int How many generations were checked. */
    public function run(): int
    {
        $stale = Generation::query()
            ->unfinished()
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->orderBy('updated_at')
            ->limit(self::BATCH_SIZE)
            ->get();

        $checked = 0;

        foreach ($stale as $generation) {
            try {
                $snapshot = $this->pixelforge->generation((string) $generation->pixelforge_id);
            } catch (TemporaryError|PixelforgeAuthError $stop) {
                Log::warning('Missed-webhook check stopped early', ['generation' => $generation->uuid, 'error' => $stop->errorCode]);

                break;
            } catch (PermanentError $error) {
                Log::warning('Missed-webhook check could not fetch a generation', ['generation' => $generation->uuid, 'error' => $error->errorCode]);
                $this->checkedNow($generation);
                $checked++;

                continue;
            }

            try {
                $this->lifecycle->apply($generation, $snapshot);
            } catch (IncompleteSuccess $incomplete) {
                Log::warning($incomplete->getMessage(), ['generation' => $generation->uuid]);
            }

            $this->checkedNow($generation);
            $checked++;
        }

        return $checked;
    }

    /** Still unfinished after a check: send it to the back of the line, so every stale one gets its turn. */
    private function checkedNow(Generation $generation): void
    {
        Generation::query()->whereKey($generation->getKey())->unfinished()->update(['updated_at' => now()]);
    }
}
