<?php

use App\Credits\Affordability;
use App\Credits\Ledger;
use App\Generations\GenerationLifecycle;
use App\Generations\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::fake(['pixelforge.test/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake();
});

/** A user whose $5.00 bonus has been spent down to $balance by an earlier generation. */
function userWithBalance(int $balance): User
{
    $user = User::factory()->create();
    $spent = Generation::factory()->for($user)->status(GenerationStatus::Succeeded)->create(['actual_charge' => 5_000_000_000 - $balance]);
    app(Ledger::class)->charge($spent);

    return $user->fresh();
}

/** The docs' worked example: its estimate top is $0.467343750. */
function costlySettings(): array
{
    return [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ];
}

test('never twice: five rapid submits with $0.50 available and a $0.47 top allow one and block the rest', function () {
    $user = userWithBalance(500_000_000);

    $allowed = collect(range(1, 5))->map(function () use ($user) {
        $before = Generation::count();
        $this->actingAs($user)->from(route('create'))->post(route('generations.store'), costlySettings());

        return Generation::count() > $before;
    });

    expect($allowed->all())->toBe([true, false, false, false, false]);
    $this->actingAs($user)->from(route('create'))->post(route('generations.store'), costlySettings())
        ->assertSessionHasErrors(['prompt' => 'Not enough credits. $0.03 available, this could cost up to $0.47.']);
    expect(Generation::whereBelongsTo($user)->unfinished()->count())->toBe(1)
        ->and(Affordability::availableBalance($user))->toBe(500_000_000 - 467_343_750);
    Queue::assertPushed(SubmitGeneration::class, 1);
});

test('a generation whose top exactly matches the available balance is allowed', function () {
    $user = userWithBalance(467_343_750);

    $this->actingAs($user)->post(route('generations.store'), costlySettings())->assertSessionHasNoErrors();

    expect(Affordability::availableBalance($user))->toBe(0);
});

test('a blocked generation saves nothing, sends nothing and keeps the balance', function () {
    $user = userWithBalance(400_000_000);

    $this->actingAs($user)->from(route('create'))->post(route('generations.store'), costlySettings())
        ->assertRedirect(route('create'))
        ->assertSessionHasErrors(['prompt' => 'Not enough credits. $0.40 available, this could cost up to $0.47.']);

    expect(Generation::whereBelongsTo($user)->unfinished()->count())->toBe(0)
        ->and($user->fresh()->balance)->toBe(400_000_000);
    Queue::assertNothingPushed();
});

test('failed is free: once a running generation fails, its set-aside amount can be used again', function () {
    $user = userWithBalance(500_000_000);
    $this->actingAs($user)->post(route('generations.store'), costlySettings());
    $running = Generation::whereBelongsTo($user)->unfinished()->sole();

    app(GenerationLifecycle::class)->failBeforeAcceptance($running, 'overloaded');

    $this->actingAs($user)->post(route('generations.store'), costlySettings())->assertSessionHasNoErrors();
    expect(Generation::whereBelongsTo($user)->unfinished()->count())->toBe(1);
});
