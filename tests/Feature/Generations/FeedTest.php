<?php

use App\Generations\GenerationStatus;
use App\Http\Middleware\HandleInertiaRequests;
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

test('a done generation carries its actual charge, capped flag and image', function () {
    Generation::factory()->for($this->user)->status(GenerationStatus::Succeeded)->create([
        'provider_cost' => 96_045_000,
        'actual_charge' => 86_337_500,
        'capped' => true,
        'absorbed' => 33_718_750,
        'image_url' => 'https://img.pixelforge.test/done.png',
        'image_url_expires_at' => '2026-11-19T10:01:00Z',
    ]);

    $this->travelTo('2026-11-18T10:01:00Z');

    $this->actingAs($this->user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.status', 'succeeded')
            ->where('generations.0.charged', 86_337_500)
            ->where('generations.0.capped', true)
            ->where('generations.0.estimate_low', 58_187_500)
            ->where('generations.0.estimate_high', 86_337_500)
            ->where('generations.0.image_url', 'https://img.pixelforge.test/done.png')
            ->where('generations.0.image_url_expires_at', '2026-11-19T10:01:00Z'));
});

test('a done generation past its image expiry shows as expired and keeps its status and actual charge', function () {
    Generation::factory()->for($this->user)->status(GenerationStatus::Succeeded)->create([
        'provider_cost' => 64_000_000,
        'actual_charge' => 80_000_000,
        'image_url' => 'https://img.pixelforge.test/old.png',
        'image_url_expires_at' => '2026-11-19T10:01:00Z',
    ]);

    $this->travelTo('2026-11-19T10:01:00Z');

    $this->actingAs($this->user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.status', 'succeeded')
            ->where('generations.0.charged', 80_000_000)
            ->where('generations.0.estimate_high', 86_337_500)
            ->where('generations.0.image_expired', true)
            ->where('generations.0.image_url', null));
});

test('a done generation before its image expiry still shows its image', function () {
    Generation::factory()->for($this->user)->status(GenerationStatus::Succeeded)->create([
        'image_url' => 'https://img.pixelforge.test/new.png',
        'image_url_expires_at' => '2026-11-19T10:01:00Z',
    ]);

    $this->travelTo('2026-11-19T10:00:59Z');

    $this->actingAs($this->user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.image_expired', false)
            ->where('generations.0.image_url', 'https://img.pixelforge.test/new.png'));
});

test('a new user\'s empty feed is told their $5.00 starting credits', function () {
    $this->actingAs($this->user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 0)
            ->where('feed.everything', 0)
            ->where('startingCredits', 5_000_000_000));
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

test('polling reloads only the generations and the feed counts', function () {
    Generation::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('create'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Create',
            'X-Inertia-Partial-Data' => 'generations,feed',
        ])
        ->assertJsonPath('props.generations.0.status', 'waiting')
        ->assertJsonPath('props.feed.total', 1)
        ->assertJsonMissingPath('props.pricing');

    Http::assertNothingSent();
});

test('the Failed filter lists only failed generations and counts them', function () {
    Generation::factory()->for($this->user)->status(GenerationStatus::Succeeded)->create();
    $failed = Generation::factory()->for($this->user)->status(GenerationStatus::Failed)->create();

    $this->actingAs($this->user)->get(route('create', ['filter' => 'failed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 1)
            ->where('generations.0.id', $failed->id)
            ->where('feed.filter', 'failed')
            ->where('feed.total', 1)
            ->where('feed.everything', 2));
});

test('the Running filter lists generations waiting for a slot, queued or processing', function () {
    Generation::factory()->for($this->user)->status(GenerationStatus::Queued)->create();
    Generation::factory()->for($this->user)->status(GenerationStatus::Processing)->create();
    Generation::factory()->for($this->user)->create();
    Generation::factory()->for($this->user)->status(GenerationStatus::Failed)->create();

    $this->actingAs($this->user)->get(route('create', ['filter' => 'running']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 3)
            ->where('feed.total', 3));
});

test('the Done filter lists only succeeded generations', function () {
    $done = Generation::factory()->for($this->user)->status(GenerationStatus::Succeeded)->create();
    Generation::factory()->for($this->user)->status(GenerationStatus::Queued)->create();

    $this->actingAs($this->user)->get(route('create', ['filter' => 'succeeded']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 1)
            ->where('generations.0.id', $done->id));
});

test('Load 20 more shows the next 20, still newest first', function () {
    $gens = Generation::factory()->for($this->user)->count(45)->create();

    $this->actingAs($this->user)->get(route('create', ['shown' => 40]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 40)
            ->where('generations.0.id', $gens->last()->id)
            ->where('generations.39.id', $gens[5]->id)
            ->where('feed.shown', 40)
            ->where('feed.total', 45));
});

test('the number shown is kept between 20 and the 20-step cap', function (mixed $asked, int $shown) {
    $this->actingAs($this->user)->get(route('create', ['shown' => $asked]))
        ->assertInertia(fn (Assert $page) => $page->where('feed.shown', $shown));
})->with([
    'missing' => [null, 20],
    'zero' => [0, 20],
    'negative' => [-5, 20],
    'not a number' => ['lots', 20],
    'between steps rounds up' => [25, 40],
    'above the cap' => [100_000, 1000],
]);

test('an unknown filter falls back to All', function () {
    Generation::factory()->for($this->user)->status(GenerationStatus::Failed)->create();
    Generation::factory()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('create', ['filter' => 'bogus']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 2)
            ->where('feed.filter', 'all')
            ->where('feed.total', 2));
});
