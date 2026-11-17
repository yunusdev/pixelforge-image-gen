<?php

namespace Database\Factories;

use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A generation just submitted: waiting for a slot, medium 1024x1024, with its estimate.
 *
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'status' => GenerationStatus::Waiting,
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            // Pixelforge's published rates for this prompt and these settings
            'estimate_low' => 58_187_500,
            'estimate_high' => 86_337_500,
            'estimate_provider_cost_low' => 46_550_000,
            'estimate_provider_cost_high' => 69_070_000,
        ];
    }

    public function status(GenerationStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'pixelforge_id' => $status === GenerationStatus::Waiting ? null : 'gen_'.Str::random(12),
        ]);
    }
}
