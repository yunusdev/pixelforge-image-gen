<?php

namespace App\Generations;

use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pricing\GenerationSettings;
use App\Pricing\Pricing;
use App\Pricing\PricingRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Save first, send in the background: a submitted generation is stored as Waiting for a slot
 * with its settings, estimate and UUID, then a queued job sends it to Pixelforge.
 *
 * Ticket 06 adds the affordability check (under a lock on the user) here.
 */
class SubmitGenerations
{
    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images: list<string>}  $settings
     */
    public function submit(User $user, array $settings, PricingRules $rules): Generation
    {
        $estimate = Pricing::estimate(new GenerationSettings(
            $settings['prompt'],
            $settings['size'],
            $settings['quality'],
            count($settings['reference_images']),
        ), $rules);

        $generation = DB::transaction(fn (): Generation => $user->generations()->create([
            'uuid' => (string) Str::uuid(),
            'status' => GenerationStatus::Waiting,
            'prompt' => $settings['prompt'],
            'size' => $settings['size'],
            'quality' => $settings['quality'],
            'reference_images' => array_values($settings['reference_images']),
            'estimate_low' => $estimate->low,
            'estimate_high' => $estimate->high,
            'estimate_provider_cost_low' => $estimate->providerCostLow,
            'estimate_provider_cost_high' => $estimate->providerCostHigh,
        ]));

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
