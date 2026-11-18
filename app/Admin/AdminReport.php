<?php

namespace App\Admin;

use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeError;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Read model for the Admin report: succeeded generations grouped by UTC completion day and by user,
 * each day checked against Pixelforge's own usage report. Amounts are integer nanodollars.
 *
 * @phpstan-type Figures array{generations: int, charged: int, providerCost: int, profit: int, capped: int, absorbed: int, estimateLow: int, estimateHigh: int, position: int|null}
 * @phpstan-type UserRow array{id: int, name: string, email: string, generations: int, charged: int, providerCost: int, profit: int, capped: int, absorbed: int, estimateLow: int, estimateHigh: int, position: int|null}
 * @phpstan-type GenerationRow array{id: int, prompt: string, user: array{id: int, name: string}, completedAt: string|null, estimateLow: int, estimateHigh: int, charged: int, providerCost: int, capped: bool, absorbed: int, position: int|null}
 */
class AdminReport
{
    public const PER_PAGE = 20;

    public function __construct(private readonly PixelforgeClient $pixelforge) {}

    /**
     * Every day in the range, newest first, with Pixelforge's own cost for it when its usage report answered.
     *
     * @return array{usageChecked: bool, totals: array<string, int|null>, days: list<array<string, mixed>>}
     */
    public function days(ReportRange $range): array
    {
        $usage = $this->usage($range);
        $byDay = $this->billed($range)
            ->selectRaw("to_char(completed_at, 'YYYY-MM-DD') as day")
            ->selectRaw(self::SUMS)
            ->groupBy('day')
            ->toBase()
            ->get()
            ->keyBy('day');

        $days = array_map(function (string $date) use ($byDay, $usage): array {
            $figures = self::figures($byDay->get($date));
            $pixelforgeCost = $usage[$date] ?? null;

            return [
                'date' => $date,
                ...$figures,
                'pixelforgeCost' => $pixelforgeCost,
                'check' => match (true) {
                    $pixelforgeCost === null => 'not_checked',
                    $pixelforgeCost === $figures['providerCost'] => 'match',
                    default => 'mismatch',
                },
                'difference' => $pixelforgeCost === null ? null : $pixelforgeCost - $figures['providerCost'],
            ];
        }, $range->dates());

        return [
            'usageChecked' => $usage !== null,
            'totals' => [
                ...self::figures($this->billed($range)->selectRaw(self::SUMS)->toBase()->first()),
                'days' => count($days),
                'matchingDays' => count(array_filter($days, fn (array $day) => $day['check'] === 'match')),
            ],
            'days' => $days,
        ];
    }

    /** @return LengthAwarePaginator<array-key, UserRow> users with generations in the range, highest charged first */
    public function users(ReportRange $range): LengthAwarePaginator
    {
        $sums = $this->billed($range)->selectRaw('user_id')->selectRaw(self::SUMS)->groupBy('user_id')->toBase();

        return DB::query()
            ->fromSub($sums, 'sums')
            ->join('users', 'users.id', '=', 'sums.user_id')
            ->select('sums.*', 'users.name', 'users.email')
            ->orderByDesc('sums.charged')
            ->orderBy('users.id')
            ->paginate(self::PER_PAGE, pageName: 'users_page')
            ->withQueryString()
            ->through(fn (stdClass $row): array => [
                'id' => (int) $row->user_id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                ...self::figures($row),
            ]);
    }

    /**
     * Each succeeded generation in the range, newest first, optionally only one UTC day's or one user's.
     *
     * @return LengthAwarePaginator<int, GenerationRow>
     */
    public function generations(ReportRange $range, ?string $day = null, ?int $userId = null): LengthAwarePaginator
    {
        return $this->succeeded($range)
            ->with('user:id,name')
            ->when($day !== null, fn (Builder $query) => $query
                ->where('completed_at', '>=', CarbonImmutable::parse($day, 'UTC'))
                ->where('completed_at', '<', CarbonImmutable::parse($day, 'UTC')->addDay()))
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'generations_page')
            ->withQueryString()
            ->through(fn (Generation $generation): array => [
                'id' => $generation->id,
                'prompt' => $generation->prompt,
                'user' => ['id' => $generation->user->id, 'name' => $generation->user->name],
                'completedAt' => $generation->completed_at?->toIso8601ZuluString(),
                'estimateLow' => $generation->estimate_low,
                'estimateHigh' => $generation->estimate_high,
                'charged' => (int) $generation->actual_charge,
                'providerCost' => (int) $generation->provider_cost,
                'capped' => $generation->capped,
                'absorbed' => $generation->absorbed,
                'position' => self::position($generation),
            ]);
    }

    /** Where one actual charge landed between its estimate's low and high, as a whole percent. */
    private static function position(Generation $generation): ?int
    {
        $span = $generation->estimate_high - $generation->estimate_low;
        if ($span <= 0) {
            return null;
        }

        $ratio = intdiv(200 * ((int) $generation->actual_charge - $generation->estimate_low) + $span, 2 * $span);

        return max(0, min(100, $ratio));
    }

    /**
     * Sums over a group of billed generations. Generation counts, charges and estimates cover successes only; the
     * provider cost and absorbed amount also include what Pixelforge billed for generations we gave up on.
     * `position` is where the actual charges landed between the estimate's low and high on average, as a
     * whole percent (display only, not money).
     */
    private const SUMS = <<<'SQL'
        count(*) filter (where status = 'succeeded') as generations,
        coalesce(sum(actual_charge) filter (where status = 'succeeded'), 0) as charged,
        coalesce(sum(provider_cost), 0) as provider_cost,
        count(*) filter (where capped) as capped,
        coalesce(sum(absorbed), 0) as absorbed,
        coalesce(sum(estimate_low) filter (where status = 'succeeded'), 0) as estimate_low,
        coalesce(sum(estimate_high) filter (where status = 'succeeded'), 0) as estimate_high,
        round(100 * avg(least(1, greatest(0, (actual_charge - estimate_low)::numeric / nullif(estimate_high - estimate_low, 0)))) filter (where status = 'succeeded')) as position
        SQL;

    /** @return Figures */
    private static function figures(?object $row): array
    {
        $charged = (int) ($row->charged ?? 0);
        $providerCost = (int) ($row->provider_cost ?? 0);

        return [
            'generations' => (int) ($row->generations ?? 0),
            'charged' => $charged,
            'providerCost' => $providerCost,
            'profit' => $charged - $providerCost,
            'capped' => (int) ($row->capped ?? 0),
            'absorbed' => (int) ($row->absorbed ?? 0),
            'estimateLow' => (int) ($row->estimate_low ?? 0),
            'estimateHigh' => (int) ($row->estimate_high ?? 0),
            'position' => isset($row->position) ? (int) $row->position : null,
        ];
    }

    /**
     * Generations Pixelforge billed us for that completed within the range: the successes, plus any we
     * gave up on that Pixelforge made anyway (failed and free, with what it billed absorbed).
     *
     * @return Builder<Generation>
     */
    private function billed(ReportRange $range): Builder
    {
        return Generation::query()
            ->where(fn (Builder $query) => $query
                ->where('status', GenerationStatus::Succeeded)
                ->orWhere(fn (Builder $query) => $query->where('status', GenerationStatus::Failed)->where('absorbed', '>', 0)))
            ->where('completed_at', '>=', $range->from)
            ->where('completed_at', '<', $range->end());
    }

    /** @return Builder<Generation> succeeded generations that completed within the range */
    private function succeeded(ReportRange $range): Builder
    {
        return Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->where('completed_at', '>=', $range->from)
            ->where('completed_at', '<', $range->end());
    }

    /** @return array<string, int>|null null when Pixelforge didn't answer */
    private function usage(ReportRange $range): ?array
    {
        try {
            return $this->pixelforge->usage($range->from, $range->to);
        } catch (PixelforgeError) {
            return null;
        }
    }
}
