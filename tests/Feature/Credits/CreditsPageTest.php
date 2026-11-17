<?php

use App\Credits\CreditPack;
use App\Credits\Ledger;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Models\LedgerEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** A generation that succeeded at $completedAt, charged through the ledger at that time. */
function chargedGeneration(User $user, int $charge, string $at, string $prompt = 'A lighthouse'): Generation
{
    test()->travelTo($at);
    $generation = Generation::factory()->for($user)->status(GenerationStatus::Succeeded)
        ->create(['actual_charge' => $charge, 'prompt' => $prompt, 'completed_at' => $at]);
    app(Ledger::class)->charge($generation);
    test()->travelBack();

    return $generation;
}

test('guests are sent to log in', function () {
    $this->get(route('credits'))->assertRedirect(route('login'));
});

test('the credits card shows the available balance, the balance and what is set aside for running images', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create(['estimate_high' => 100_000_000]);
    Generation::factory()->for($user)->status(GenerationStatus::Processing)->create(['estimate_high' => 200_000_000]);

    $this->actingAs($user)->get(route('credits'))->assertInertia(fn (Assert $page) => $page
        ->component('Credits')
        ->where('summary', [
            'available' => 4_700_000_000,
            'balance' => 5_000_000_000,
            'setAside' => 300_000_000,
            'running' => 2,
        ])
        ->where('packs', [5, 10, 25]));
});

test('a first-time user sees only the sign-up bonus in the ledger and no spending', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('credits'))->assertInertia(fn (Assert $page) => $page
        ->has('ledger.data', 1)
        ->where('ledger.data.0.label', 'Sign-up bonus')
        ->where('ledger.data.0.amount', 5_000_000_000)
        ->where('ledger.total', 1)
        ->where('spending.total', 0)
        ->has('spending.days', 30));
});

test('the ledger lists charges with their prompt, newest first, and only the user\'s own', function () {
    $user = User::factory()->create();
    chargedGeneration($user, 65_056_250, '2026-11-17 09:00:00', 'Neon koi pond');
    app(Ledger::class)->buyPack(User::factory()->create(), CreditPack::Ten);

    $this->actingAs($user)->get(route('credits'))->assertInertia(fn (Assert $page) => $page
        ->has('ledger.data', 2)
        ->where('ledger.data.0.label', 'Image charge')
        ->where('ledger.data.0.prompt', 'Neon koi pond')
        ->where('ledger.data.0.amount', -65_056_250)
        ->where('ledger.data.1.label', 'Sign-up bonus'));
});

test('the ledger is paginated 20 entries at a time', function () {
    $user = User::factory()->create();
    foreach (range(1, 24) as $_) {
        app(Ledger::class)->buyPack($user, CreditPack::Five);
    }

    $this->actingAs($user)->get(route('credits', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('ledger.data', 5)
        ->where('ledger.current_page', 2)
        ->where('ledger.last_page', 2)
        ->where('ledger.total', 25));
});

test('the spending chart sums the last 30 UTC days of charges, oldest first', function () {
    $this->travelTo('2026-11-18 12:00:00');
    $user = User::factory()->create();
    chargedGeneration($user, 100_000_000, '2026-11-18 08:00:00');
    chargedGeneration($user, 50_000_000, '2026-11-18 23:30:00');
    chargedGeneration($user, 30_000_000, '2026-10-20 10:00:00');
    chargedGeneration($user, 70_000_000, '2026-10-19 23:59:59');
    $this->travelTo('2026-11-18 12:00:00');

    $this->actingAs($user)->get(route('credits'))->assertInertia(fn (Assert $page) => $page
        ->has('spending.days', 30)
        ->where('spending.days.0', ['date' => '2026-10-20', 'amount' => 30_000_000])
        ->where('spending.days.29', ['date' => '2026-11-18', 'amount' => 150_000_000])
        ->where('spending.total', 180_000_000));
});

test('buying a pack writes one ledger entry, confirms it in a toast and highlights the new line', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('credits.packs.store'), ['pack' => 10])
        ->assertRedirect(route('credits'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Added $10.00. You now have $15.00 available.'])
        ->assertInertiaFlash('highlight', LedgerEntry::where('amount', 10_000_000_000)->sole()->id);

    expect($user->ledgerEntries()->count())->toBe(2)
        ->and($user->fresh()->balance)->toBe(15_000_000_000);
});

test('only the $5, $10 and $25 packs can be bought', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('credits.packs.store'), ['pack' => 1000])
        ->assertSessionHasErrors('pack');

    expect($user->ledgerEntries()->count())->toBe(1);
});

test('every page shares the available balance for the top bar and account menu', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create(['estimate_high' => 86_337_500]);

    $this->actingAs($user)->get(route('create'))->assertInertia(fn (Assert $page) => $page
        ->where('credits.available', 5_000_000_000 - 86_337_500));
});
