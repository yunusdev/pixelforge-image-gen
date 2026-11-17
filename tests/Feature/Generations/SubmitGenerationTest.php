<?php

use App\Generations\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->pricingReply = fn () => Http::response(pixelforgePricing());
    Http::fake(['pixelforge.test/pricing' => fn () => ($this->pricingReply)()]);
    Queue::fake();
});

/** The docs' worked example: high, 1536x1024, 400 characters, 2 references. */
function workedExample(): array
{
    return [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ];
}

test('submitting saves the generation as waiting for a slot, with its settings, estimate and a UUID', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('generations.store'), workedExample())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('create'));

    $generation = Generation::sole();
    expect($generation->user_id)->toBe($user->id)
        ->and($generation->status)->toBe(GenerationStatus::Waiting)
        ->and(Str::isUuid($generation->uuid))->toBeTrue()
        ->and($generation->prompt)->toBe(str_repeat('a', 400))
        ->and($generation->size)->toBe('1536x1024')
        ->and($generation->quality)->toBe('high')
        ->and($generation->reference_images)->toBe(['https://example.com/a.png', 'https://example.com/b.png'])
        ->and($generation->pixelforge_id)->toBeNull();
});

test('exact cost: the saved estimate is the worked example, top $0.467343750 from a maximum provider cost of $0.373875000', function () {
    $this->actingAs(User::factory()->create())->post(route('generations.store'), workedExample());

    $generation = Generation::sole();
    expect($generation->estimate_provider_cost_high)->toBe(373_875_000)
        ->and($generation->estimate_high)->toBe(467_343_750)
        ->and($generation->estimate_provider_cost_low)->toBe(286_225_000)
        ->and($generation->estimate_low)->toBe(357_781_250);
});

test('submitting sends the generation to Pixelforge in the background and returns at once', function () {
    $this->actingAs(User::factory()->create())->post(route('generations.store'), workedExample());

    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is(Generation::sole()));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/generations'));
});

test('the confirmation says how much is set aside, rounded up to the cent', function () {
    $this->actingAs(User::factory()->create())->post(route('generations.store'), workedExample())
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Started. Up to $0.47 set aside until it finishes.']);
});

test('without pricing from Pixelforge nothing is saved and the bar explains why', function () {
    $this->pricingReply = fn () => Http::response('down', 503);

    $this->actingAs(User::factory()->create())
        ->from(route('create'))
        ->post(route('generations.store'), workedExample())
        ->assertSessionHasErrors(['prompt' => "Prices couldn't be loaded from Pixelforge. Try again in a moment."]);

    expect(Generation::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('invalid settings save nothing', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('generations.store'), [...workedExample(), 'prompt' => ''])
        ->assertSessionHasErrors('prompt');

    expect(Generation::count())->toBe(0);
});
