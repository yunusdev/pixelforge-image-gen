<?php

use App\Generations\GenerationLifecycle;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeGeneration;

function snapshot(array $overrides = []): PixelforgeGeneration
{
    return PixelforgeGeneration::fromArray(pixelforgeGeneration(['id' => 'gen_life', ...$overrides]));
}

function succeededSnapshot(array $rates = []): PixelforgeGeneration
{
    return snapshot([
        'status' => 'succeeded',
        'usage' => ['input_text_tokens' => 9, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 640, 'total_tokens' => 1749],
        'rates' => [...pixelforgeGeneration()['rates'], ...$rates],
        'image_url' => 'https://img.pixelforge.test/gen_life.png',
        'image_url_expires_at' => '2026-11-19T10:01:00Z',
        'completed_at' => '2026-11-18T10:01:00Z',
    ]);
}

test('statuses only move forward', function () {
    $generation = Generation::factory()->create();
    $lifecycle = app(GenerationLifecycle::class);

    $lifecycle->apply($generation, snapshot(['status' => 'processing']));
    $lifecycle->apply($generation, snapshot(['status' => 'queued']));

    expect($generation->fresh()->status)->toBe(GenerationStatus::Processing);
});

test('exact cost: a succeeded snapshot records usage × the locked rates, plus 25%', function () {
    $generation = Generation::factory()->create();

    app(GenerationLifecycle::class)->apply($generation, succeededSnapshot());

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->provider_cost->toBe(52_045_000)
        ->actual_charge->toBe(65_056_250)
        ->capped->toBeFalse()
        ->image_url->toBe('https://img.pixelforge.test/gen_life.png');
});

test('exact cost: a charge above the estimate top is capped and the excess absorbed', function () {
    $generation = Generation::factory()->create(['estimate_high' => 86_337_500]);

    app(GenerationLifecycle::class)->apply($generation, succeededSnapshot(['output_tokens' => '80.00']));

    expect($generation->fresh())
        ->provider_cost->toBe(96_045_000)
        ->actual_charge->toBe(86_337_500)
        ->capped->toBeTrue()
        ->absorbed->toBe(33_718_750);
});

test('never twice: a final generation never changes again', function () {
    $generation = Generation::factory()->create();
    $lifecycle = app(GenerationLifecycle::class);

    $lifecycle->apply($generation, succeededSnapshot());
    $lifecycle->apply($generation, succeededSnapshot(['output_tokens' => '80.00']));
    $lifecycle->apply($generation, snapshot(['status' => 'failed', 'error' => ['code' => 'internal_error', 'message' => 'x']]));

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->actual_charge->toBe(65_056_250);
});

test('failed is free: a failed snapshot records its reason and no charge', function () {
    $generation = Generation::factory()->create();

    app(GenerationLifecycle::class)->apply($generation, snapshot([
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'Could not render.'],
    ]));

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->error_code->toBe('generation_failed')
        ->actual_charge->toBeNull()
        ->charged()->toBe(0);
});
