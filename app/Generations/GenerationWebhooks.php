<?php

namespace App\Generations;

use App\Models\Generation;
use App\Pixelforge\PixelforgeGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Processes one verified Pixelforge webhook event, in the request and in one transaction (ADR-0003):
 * record the event id (unique, so a redelivery does nothing), find the generation, and hand the
 * snapshot to the lifecycle, which locks the row and only moves it forward.
 *
 * If anything throws, the transaction (event id included) rolls back and Pixelforge retries.
 */
class GenerationWebhooks
{
    public function __construct(private readonly GenerationLifecycle $lifecycle) {}

    /** @param  array{id: string, type: string, data?: array<string, mixed>}  $event */
    public function handle(array $event): void
    {
        $object = $event['data']['object'] ?? null;
        $isGeneration = is_array($object) && ($object['object'] ?? null) === 'generation' && isset($object['id'], $object['status']);

        DB::transaction(function () use ($event, $object, $isGeneration): void {
            $recorded = DB::table('processed_webhook_events')->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'pixelforge_generation_id' => $isGeneration ? (string) $object['id'] : null,
                'received_at' => now(),
            ]);

            if ($recorded === 0) {
                Log::info('Pixelforge webhook already processed', ['event' => $event['id']]);

                return;
            }

            if (! $isGeneration) {
                Log::info("Pixelforge webhook {$event['type']} acknowledged", ['event' => $event['id']]);

                return;
            }

            $snapshot = PixelforgeGeneration::fromArray($object);
            $generation = $this->find($snapshot);

            if ($generation === null) {
                Log::info('Pixelforge webhook for an unknown generation', ['event' => $event['id'], 'pixelforge_id' => $snapshot->id]);

                return;
            }

            $this->lifecycle->apply($generation, $snapshot);
        });
    }

    /** By Pixelforge id, or by our UUID in metadata when the event beats the create call's reply. */
    private function find(PixelforgeGeneration $snapshot): ?Generation
    {
        return Generation::query()->where('pixelforge_id', $snapshot->id)->first()
            ?? (! Str::isUuid($snapshot->generationUuid) ? null : Generation::query()
                ->where('uuid', $snapshot->generationUuid)
                ->whereNull('pixelforge_id')
                ->first());
    }
}
