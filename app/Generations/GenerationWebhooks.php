<?php

namespace App\Generations;

use Illuminate\Support\Facades\Log;

/**
 * Processes one verified Pixelforge webhook event.
 */
class GenerationWebhooks
{
    /** @param  array{id: string, type: string, data?: array<string, mixed>}  $event */
    public function handle(array $event): void
    {
        Log::info('Pixelforge webhook received', ['event' => $event['id'], 'type' => $event['type']]);
    }
}
