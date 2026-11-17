<?php

namespace App\Generations;

use App\Credits\Affordability;
use App\Credits\NotEnoughCredits;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pricing\GenerationSettings;
use App\Pricing\Pricing;
use App\Pricing\PricingRules;
use Illuminate\Support\Str;

/**
 * Save first, send in the background: a submitted generation is stored as Waiting for a slot
 * with its settings, estimate and UUID, then a queued job sends it to Pixelforge.
 *
 * It's saved only if the user's available balance covers its estimate top, checked under a
 * lock on the user so rapid submits can't overspend together (ADR-0002).
 */
class SubmitGenerations
{
    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images: array<int, string>}  $settings
     *
     * @throws NotEnoughCredits
     */
    public function submit(User $user, array $settings, PricingRules $rules): Generation
    {
        $estimate = Pricing::estimate(new GenerationSettings(
            $settings['prompt'],
            $settings['size'],
            $settings['quality'],
            count($settings['reference_images']),
        ), $rules);

        $generation = Affordability::startIfAffordable($user, $estimate->high, fn (): Generation => $user->generations()->create([
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
