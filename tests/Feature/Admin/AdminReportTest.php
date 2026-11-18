<?php

use App\Generations\GenerationStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A generation that succeeded at $completedAt with this provider cost, charged cost + 25% rounded up,
 * capped at the factory estimate's top (58_187_500 to 86_337_500).
 */
function succeededAt(string $completedAt, int $providerCost, ?User $user = null, array $overrides = []): Generation
{
    $raw = intdiv($providerCost * 125 + 99, 100);
    $high = $overrides['estimate_high'] ?? 86_337_500;

    return Generation::factory()->for($user ?? User::factory())->status(GenerationStatus::Succeeded)->create([
        'created_at' => $overrides['created_at'] ?? $completedAt,
        'completed_at' => $completedAt,
        'provider_cost' => $providerCost,
        'actual_charge' => min($raw, $high),
        'capped' => $raw > $high,
        'absorbed' => max(0, $raw - $high),
        ...$overrides,
    ]);
}

/** The report's row for one day. */
function reportDay(Assert $page, string $date): array
{
    return collect($page->toArray()['props']['days'])->firstWhere('date', $date);
}

beforeEach(function () {
    $this->travelTo('2026-11-18 12:00:00');
    $this->admin = User::factory()->admin()->create(['name' => 'Admin']);
});

test('the range defaults to the last 30 UTC days and is checked against Pixelforge usage for the same days', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);

    $this->actingAs($this->admin)->get(route('admin.report'))->assertInertia(fn (Assert $page) => $page
        ->where('range', ['from' => '2026-10-20', 'to' => '2026-11-18', 'maxDays' => 92])
        ->has('days', 30)
        ->where('days.0.date', '2026-11-18')
        ->where('days.29.date', '2026-10-20'));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://pixelforge.test/usage?start_date=2026-10-20&end_date=2026-11-18');
});

test('a range of up to 92 days can be picked', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);

    $this->actingAs($this->admin)->get(route('admin.report', ['from' => '2026-08-19', 'to' => '2026-11-18']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('range.from', '2026-08-19')
            ->has('days', 92));
});

test('ranges longer than 92 days or ending before they start are refused', function (array $query, string $field) {
    Http::fake();

    $this->actingAs($this->admin)->get(route('admin.report', $query))->assertSessionHasErrors($field);

    Http::assertNothingSent();
})->with([
    '93 days' => [['from' => '2026-08-18', 'to' => '2026-11-18'], 'from'],
    'backwards' => [['from' => '2026-11-10', 'to' => '2026-11-01'], 'to'],
    'not a date' => [['from' => 'yesterday'], 'from'],
]);

test('exact cost: a generation created at 23:59 UTC and completed at 00:00 UTC counts on the completion day, and day totals equal the sum of their generations', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);
    succeededAt('2026-11-17 00:00:00', 46_550_000, overrides: ['created_at' => '2026-11-16 23:59:00']);
    succeededAt('2026-11-17 15:00:00', 60_000_000);
    succeededAt('2026-11-16 23:59:59', 10_000_000);

    $this->actingAs($this->admin)->get(route('admin.report'))->assertInertia(function (Assert $page) {
        expect(reportDay($page, '2026-11-17'))->toMatchArray([
            'images' => 2,
            'charged' => 58_187_500 + 75_000_000,
            'providerCost' => 46_550_000 + 60_000_000,
            'profit' => 133_187_500 - 106_550_000,
        ])->and(reportDay($page, '2026-11-16'))->toMatchArray([
            'images' => 1,
            'charged' => 12_500_000,
            'providerCost' => 10_000_000,
            'profit' => 2_500_000,
        ])->and(reportDay($page, '2026-11-15'))->toMatchArray([
            'images' => 0,
            'charged' => 0,
            'providerCost' => 0,
            'profit' => 0,
        ]);
    });
});

test('each day counts its capped charges with the amount absorbed, and sums the estimate range with where actuals landed', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);
    succeededAt('2026-11-17 09:00:00', 46_550_000); // charged 58_187_500: the bottom of the range
    succeededAt('2026-11-17 10:00:00', 60_000_000); // charged 75_000_000: 59.7% of the way up
    succeededAt('2026-11-17 11:00:00', 80_000_000); // 100_000_000 capped at 86_337_500

    $this->actingAs($this->admin)->get(route('admin.report'))->assertInertia(function (Assert $page) {
        expect(reportDay($page, '2026-11-17'))->toMatchArray([
            'images' => 3,
            'charged' => 58_187_500 + 75_000_000 + 86_337_500,
            'capped' => 1,
            'absorbed' => 13_662_500,
            'estimateLow' => 3 * 58_187_500,
            'estimateHigh' => 3 * 86_337_500,
            'position' => 53,
        ])->and(reportDay($page, '2026-11-16'))->toMatchArray([
            'capped' => 0,
            'absorbed' => 0,
            'position' => null,
        ]);
    });
});

test('failed is free: failed generations add nothing to charged or provider cost', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);
    succeededAt('2026-11-17 09:00:00', 46_550_000);
    // Usage numbers on a failed generation must still never count.
    Generation::factory()->status(GenerationStatus::Failed)->create([
        'completed_at' => '2026-11-17 10:00:00', 'provider_cost' => 60_000_000, 'actual_charge' => 75_000_000, 'error_code' => 'generation_failed',
    ]);
    Generation::factory()->status(GenerationStatus::Processing)->create(['completed_at' => null]);

    $this->actingAs($this->admin)->get(route('admin.report'))->assertInertia(function (Assert $page) {
        expect(reportDay($page, '2026-11-17'))->toMatchArray([
            'images' => 1,
            'charged' => 58_187_500,
            'providerCost' => 46_550_000,
        ]);
    });
});

test('each day\'s provider cost is compared with Pixelforge\'s usage report: a match, or a mismatch with the difference', function () {
    $usage = array_fill_keys(array_map(fn (int $d) => sprintf('2026-11-%02d', $d), range(1, 18)), '0.000000000');
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([
        ...$usage,
        '2026-11-17' => '0.106550000',
        '2026-11-16' => '0.010000001',
    ]))]);
    succeededAt('2026-11-17 09:00:00', 46_550_000);
    succeededAt('2026-11-17 10:00:00', 60_000_000);
    succeededAt('2026-11-16 10:00:00', 10_000_000);

    $this->actingAs($this->admin)->get(route('admin.report', ['from' => '2026-11-01']))->assertInertia(function (Assert $page) {
        expect(reportDay($page, '2026-11-17'))->toMatchArray(['pixelforgeCost' => 106_550_000, 'check' => 'match', 'difference' => 0])
            ->and(reportDay($page, '2026-11-16'))->toMatchArray(['pixelforgeCost' => 10_000_001, 'check' => 'mismatch', 'difference' => 1])
            ->and(reportDay($page, '2026-11-15'))->toMatchArray(['pixelforgeCost' => 0, 'check' => 'match']);
        $page->where('usageChecked', true);
    });
});

test('when Pixelforge\'s usage report doesn\'t respond, our numbers still show and every day is not checked', function (Closure $reply) {
    Http::fake(['pixelforge.test/usage*' => $reply]);
    succeededAt('2026-11-17 09:00:00', 46_550_000);

    $this->actingAs($this->admin)->get(route('admin.report'))->assertOk()->assertInertia(function (Assert $page) {
        expect(reportDay($page, '2026-11-17'))->toMatchArray([
            'providerCost' => 46_550_000, 'pixelforgeCost' => null, 'check' => 'not_checked', 'difference' => null,
        ]);
        $page->where('usageChecked', false);
    });
})->with([
    'overloaded' => fn () => fn () => pixelforgeError('overloaded', 503, 2),
    'unreachable' => fn () => fn () => throw new ConnectionException('timed out'),
    'bad key' => fn () => fn () => pixelforgeError('invalid_api_key', 401),
]);

test('exact cost totals for the whole range sum every day, outside the range counts nowhere, and say how many days matched', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([
        '2026-11-18' => '0.000000000',
        '2026-11-17' => '0.046550000',
        '2026-11-16' => '0.100000000',
    ]))]);
    succeededAt('2026-11-17 09:00:00', 46_550_000);
    succeededAt('2026-11-16 23:59:59', 80_000_000); // capped: charged 86_337_500, absorbed 13_662_500
    succeededAt('2026-11-15 23:59:59', 10_000_000); // before the range

    $this->actingAs($this->admin)->get(route('admin.report', ['from' => '2026-11-16']))->assertInertia(fn (Assert $page) => $page
        ->where('totals', [
            'images' => 2,
            'charged' => 144_525_000,
            'providerCost' => 126_550_000,
            'profit' => 17_975_000,
            'capped' => 1,
            'absorbed' => 13_662_500,
            'estimateLow' => 116_375_000,
            'estimateHigh' => 172_675_000,
            'position' => 50,
            'days' => 3,
            'matchingDays' => 2,
        ]));
});

test('the by-user table lists users who had images in the range, highest charged first, 20 a page', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);
    $ada = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);
    succeededAt('2026-11-17 09:00:00', 46_550_000, $ada);
    succeededAt('2026-11-16 09:00:00', 80_000_000, $ada);
    succeededAt('2026-11-17 09:00:00', 80_000_000, overrides: ['completed_at' => '2026-10-01 00:00:00']); // before the range
    foreach (range(1, 20) as $i) {
        succeededAt('2026-11-10 09:00:00', 1_000_000 * $i);
    }

    $this->actingAs($this->admin)->get(route('admin.report'))->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 20)
        ->where('users.total', 21)
        ->where('users.data.0', [
            'id' => $ada->id,
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'images' => 2,
            'charged' => 144_525_000,
            'providerCost' => 126_550_000,
            'profit' => 17_975_000,
            'capped' => 1,
            'absorbed' => 13_662_500,
            'estimateLow' => 116_375_000,
            'estimateHigh' => 172_675_000,
            'position' => 50,
        ])
        ->where('users.data.1.charged', 25_000_000));

    $this->actingAs($this->admin)->get(route('admin.report', ['users_page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.providerCost', 1_000_000));
});

test('paging the by-user table reloads only that table, without asking Pixelforge again', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);
    succeededAt('2026-11-17 09:00:00', 46_550_000);

    $this->actingAs($this->admin)
        ->get(route('admin.report', ['users_page' => 1]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'admin/Report',
            'X-Inertia-Partial-Data' => 'users',
        ])
        ->assertJsonPath('props.users.data.0.providerCost', 46_550_000)
        ->assertJsonMissingPath('props.days');

    Http::assertNothingSent();
});

test('the images table lists each succeeded image in the range, newest first, 20 a page', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);
    $ada = User::factory()->create(['name' => 'Ada']);
    $capped = succeededAt('2026-11-17 09:00:00', 80_000_000, $ada, ['prompt' => 'A lighthouse at dusk']);
    foreach (range(1, 20) as $i) {
        succeededAt('2026-11-10 09:00:00', 1_000_000 * $i);
    }
    succeededAt('2026-10-01 09:00:00', 46_550_000); // before the range

    $this->actingAs($this->admin)->get(route('admin.report'))->assertInertia(fn (Assert $page) => $page
        ->where('images.total', 21)
        ->has('images.data', 20)
        ->where('images.data.0', [
            'id' => $capped->id,
            'prompt' => 'A lighthouse at dusk',
            'user' => ['id' => $ada->id, 'name' => 'Ada'],
            'completedAt' => '2026-11-17T09:00:00Z',
            'estimateLow' => 58_187_500,
            'estimateHigh' => 86_337_500,
            'charged' => 86_337_500,
            'providerCost' => 80_000_000,
            'capped' => true,
            'absorbed' => 13_662_500,
            'position' => 100,
        ])
        ->where('filter', ['day' => null, 'user' => null]));

    $this->actingAs($this->admin)->get(route('admin.report', ['images_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('images.data', 1));
});

test('the images table filters to a clicked day or user', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([]))]);
    $ada = User::factory()->create(['name' => 'Ada']);
    $adaOn17 = succeededAt('2026-11-17 00:00:00', 46_550_000, $ada, ['created_at' => '2026-11-16 23:59:00']);
    $adaOn16 = succeededAt('2026-11-16 09:00:00', 46_550_000, $ada);
    $otherOn17 = succeededAt('2026-11-17 09:00:00', 46_550_000);

    $this->actingAs($this->admin)->get(route('admin.report', ['day' => '2026-11-17']))->assertInertia(fn (Assert $page) => $page
        ->where('images.data.0.id', $otherOn17->id)
        ->where('images.data.1.id', $adaOn17->id)
        ->has('images.data', 2)
        ->where('filter', ['day' => '2026-11-17', 'user' => null]));

    $this->actingAs($this->admin)->get(route('admin.report', ['user' => $ada->id]))->assertInertia(fn (Assert $page) => $page
        ->where('images.data.0.id', $adaOn17->id)
        ->where('images.data.1.id', $adaOn16->id)
        ->has('images.data', 2)
        ->where('filter', ['day' => null, 'user' => ['id' => $ada->id, 'name' => 'Ada']]));
});

test('an image filter outside the range or for an unknown user is refused', function (array $query, string $field) {
    Http::fake();

    $this->actingAs($this->admin)->get(route('admin.report', $query))->assertSessionHasErrors($field);
    Http::assertNothingSent();
})->with([
    'day before the range' => [['day' => '2026-10-19'], 'day'],
    'not a day' => [['day' => 'today'], 'day'],
    'unknown user' => [['user' => 999_999], 'user'],
]);

test('never twice: each generation contributes once to every total, and the comparison against a faked usage reply matches', function () {
    Http::fake(['pixelforge.test/usage*' => Http::response(pixelforgeUsage([
        '2026-11-18' => '0.000000000',
        '2026-11-17' => '0.126550000',
        '2026-11-16' => '0.010000000',
    ]))]);
    [$ada, $bo] = User::factory()->count(2)->create();
    succeededAt('2026-11-17 00:00:00', 46_550_000, $ada, ['created_at' => '2026-11-16 23:59:00']);
    succeededAt('2026-11-17 18:00:00', 80_000_000, $bo);
    succeededAt('2026-11-16 08:00:00', 10_000_000, $ada);

    $this->actingAs($this->admin)->get(route('admin.report', ['from' => '2026-11-16']))->assertInertia(function (Assert $page) {
        $props = $page->toArray()['props'];
        $charged = 58_187_500 + 86_337_500 + 12_500_000;
        $providerCost = 46_550_000 + 80_000_000 + 10_000_000;

        foreach (['days', 'users.data', 'images.data'] as $table) {
            $rows = collect(data_get($props, $table));
            expect($rows->sum('charged'))->toBe($charged, $table)
                ->and($rows->sum('providerCost'))->toBe($providerCost, $table);
        }
        expect(collect($props['days'])->sum('images'))->toBe(3)
            ->and(collect($props['users']['data'])->sum('images'))->toBe(3)
            ->and($props['images']['total'])->toBe(3)
            ->and($props['totals'])->toMatchArray(['images' => 3, 'charged' => $charged, 'providerCost' => $providerCost, 'matchingDays' => 3, 'days' => 3])
            ->and(collect($props['days'])->pluck('check')->unique()->all())->toBe(['match']);
    });
});
