<?php

use App\Credits\Affordability;
use App\Credits\CreditPack;
use App\Credits\Ledger;
use App\Credits\LedgerReason;
use App\Generations\GenerationLifecycle;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Pixelforge\PixelforgeGeneration;
use Illuminate\Database\UniqueConstraintViolationException;

/** What Pixelforge reports for a generation that succeeded with known usage (charge $0.065056250 at published rates). */
function succeededWithUsage(array $rates = []): PixelforgeGeneration
{
    return PixelforgeGeneration::fromArray(pixelforgeGeneration([
        'id' => 'gen_ledger',
        'status' => 'succeeded',
        'usage' => ['input_text_tokens' => 9, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 640, 'total_tokens' => 1749],
        'rates' => [...pixelforgeGeneration()['rates'], ...$rates],
        'completed_at' => '2026-11-18T10:01:00Z',
    ]));
}

function failedSnapshot(): PixelforgeGeneration
{
    return PixelforgeGeneration::fromArray(pixelforgeGeneration([
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'Could not render.'],
    ]));
}

test('signing up writes a $5.00 sign-up bonus ledger entry', function () {
    $this->post(route('register.store'), [
        'name' => 'Sam Okafor',
        'email' => 'sam@example.com',
        'password' => 'secret1',
        'password_confirmation' => 'secret1',
    ]);

    $user = User::where('email', 'sam@example.com')->sole();
    $entry = $user->ledgerEntries()->sole();

    expect($entry->reason)->toBe(LedgerReason::SignUpBonus)
        ->and($entry->amount)->toBe(5_000_000_000)
        ->and($user->balance)->toBe(5_000_000_000);
});

test('every user starts with the $5.00 sign-up bonus', function () {
    $user = User::factory()->create();

    expect($user->fresh()->balance)->toBe(5_000_000_000)
        ->and($user->ledgerEntries()->sole()->reason)->toBe(LedgerReason::SignUpBonus);
});

test('exact cost: a succeeded generation writes one ledger entry and the balance drops by exactly its actual charge', function () {
    $generation = Generation::factory()->create();

    app(GenerationLifecycle::class)->apply($generation, succeededWithUsage());

    $entry = LedgerEntry::where('reason', LedgerReason::GenerationCharge)->sole();
    expect($entry->amount)->toBe(-65_056_250)
        ->and($entry->generation_id)->toBe($generation->id)
        ->and($generation->user->fresh()->balance)->toBe(5_000_000_000 - 65_056_250);
});

test('exact cost: a capped charge takes only the estimate top from the balance', function () {
    $generation = Generation::factory()->create(['estimate_high' => 86_337_500]);

    app(GenerationLifecycle::class)->apply($generation, succeededWithUsage(['output_tokens' => '80.00']));

    expect($generation->user->fresh()->balance)->toBe(5_000_000_000 - 86_337_500);
});

test('never twice: a generation reported succeeded again adds no second ledger entry', function () {
    $generation = Generation::factory()->create();
    $lifecycle = app(GenerationLifecycle::class);

    $lifecycle->apply($generation, succeededWithUsage());
    $lifecycle->apply($generation, succeededWithUsage());

    expect(LedgerEntry::where('generation_id', $generation->id)->count())->toBe(1)
        ->and($generation->user->fresh()->balance)->toBe(5_000_000_000 - 65_056_250);
});

test('never twice: the database refuses a second charge entry for the same generation', function () {
    $generation = Generation::factory()->create(['actual_charge' => 65_056_250]);
    app(Ledger::class)->charge($generation);

    expect(fn () => app(Ledger::class)->charge($generation))->toThrow(UniqueConstraintViolationException::class);
});

test('failed is free: a failed generation adds no ledger entry and frees its set-aside amount', function () {
    $generation = Generation::factory()->create(['estimate_high' => 86_337_500]);
    $user = $generation->user;
    expect(Affordability::availableBalance($user))->toBe(5_000_000_000 - 86_337_500);

    app(GenerationLifecycle::class)->apply($generation, failedSnapshot());

    expect($user->ledgerEntries()->count())->toBe(1)
        ->and($user->fresh()->balance)->toBe(5_000_000_000)
        ->and(Affordability::availableBalance($user->fresh()))->toBe(5_000_000_000);
});

test('buying a credit pack writes one ledger entry and adds it to the balance', function () {
    $user = User::factory()->create();

    $entry = app(Ledger::class)->buyPack($user, CreditPack::Ten);

    expect($entry->reason)->toBe(LedgerReason::CreditPack)
        ->and($entry->amount)->toBe(10_000_000_000)
        ->and($user->fresh()->balance)->toBe(15_000_000_000);
});

test('the cached balance always equals the sum of the ledger entries', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    $lifecycle = app(GenerationLifecycle::class);
    $first = Generation::factory()->for($user)->create();

    $ledger->buyPack($user, CreditPack::Five);
    $lifecycle->apply(Generation::factory()->for($user)->create(), failedSnapshot());
    $ledger->buyPack($user, CreditPack::TwentyFive);
    $lifecycle->apply($first, succeededWithUsage());
    $lifecycle->apply($first, succeededWithUsage());

    expect($user->fresh()->balance)->toBe((int) $user->ledgerEntries()->sum('amount'))
        ->and($user->fresh()->balance)->toBe(35_000_000_000 - 65_056_250);
});

test('available balance is the balance minus the estimate top of each unfinished generation', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create(['estimate_high' => 100_000_000]);
    Generation::factory()->for($user)->status(GenerationStatus::Processing)->create(['estimate_high' => 200_000_000]);
    Generation::factory()->for($user)->status(GenerationStatus::Failed)->create(['estimate_high' => 400_000_000]);
    Generation::factory()->create(['estimate_high' => 800_000_000]);

    expect(Affordability::setAside($user))->toBe(300_000_000)
        ->and(Affordability::availableBalance($user))->toBe(5_000_000_000 - 300_000_000);
});
