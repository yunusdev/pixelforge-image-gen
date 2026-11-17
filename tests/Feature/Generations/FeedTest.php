<?php

use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::fake(['pixelforge.test/pricing' => Http::response(pixelforgePricing())]);
    $this->user = User::factory()->create();
});

test('the feed lists the user\'s generations newest first, with status, settings and estimate', function () {
    $older = Generation::factory()->for($this->user)->status(GenerationStatus::Queued)->create(['prompt' => 'Older']);
    $newer = Generation::factory()->for($this->user)->create([
        'prompt' => 'Newer',
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/a.png'],
    ]);

    $this->actingAs($this->user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 2)
            ->where('generations.0.id', $newer->id)
            ->where('generations.0.status', 'waiting')
            ->where('generations.0.prompt', 'Newer')
            ->where('generations.0.size', '1536x1024')
            ->where('generations.0.quality', 'high')
            ->where('generations.0.reference_images', ['https://example.com/a.png'])
            ->where('generations.0.estimate_low', 58_187_500)
            ->where('generations.0.estimate_high', 86_337_500)
            ->where('generations.0.failure', null)
            ->where('generations.1.id', $older->id)
            ->where('generations.1.status', 'queued'));
});

test('a failed generation carries its plain-words reason and costs nothing', function () {
    Generation::factory()->for($this->user)->status(GenerationStatus::Failed)->create(['error_code' => 'content_policy_violation']);

    $this->actingAs($this->user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.status', 'failed')
            ->where('generations.0.charged', 0)
            ->where('generations.0.failure.title', 'Prompt blocked')
            ->where('generations.0.failure.body', 'Pixelforge rejected this prompt. Try rewording it.'));
});

test('users only see their own generations', function () {
    Generation::factory()->create();

    $this->actingAs($this->user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page->has('generations', 0));
});

test('the feed shows the newest 20', function () {
    Generation::factory()->for($this->user)->count(21)->create();

    $this->actingAs($this->user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page->has('generations', 20));
});

test('polling reloads only the generations', function () {
    Generation::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('create'), [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Create',
            'X-Inertia-Partial-Data' => 'generations',
        ])
        ->assertJsonPath('props.generations.0.status', 'waiting')
        ->assertJsonMissingPath('props.pricing');

    Http::assertNothingSent();
});
