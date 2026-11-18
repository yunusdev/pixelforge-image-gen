<?php

namespace App\Http\Controllers;

use App\Admin\AdminReport;
use App\Admin\ReportRange;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The Admin report: charges, Pixelforge cost and profit per UTC day and per user. Amounts in nanodollars. */
class AdminReportController extends Controller
{
    public function __invoke(Request $request, AdminReport $report): Response
    {
        $validated = $request->validate([
            'from' => ['bail', 'nullable', 'date_format:Y-m-d', $this->withinMaxDays($request)],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $range = ReportRange::of($validated['from'] ?? null, $validated['to'] ?? null);

        $filter = $request->validate([
            'day' => ['bail', 'nullable', 'date_format:Y-m-d', function (string $attribute, string $value, Closure $fail) use ($range): void {
                if (! $range->includes($value)) {
                    $fail('Pick a day within the range.');
                }
            }],
            'user' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $day = $filter['day'] ?? null;
        $user = isset($filter['user']) ? User::query()->select('id', 'name')->findOrFail((int) $filter['user']) : null;

        // Asked once per request, and only when these props are wanted: paging a table doesn't re-ask Pixelforge.
        $days = null;
        $byDay = function () use (&$days, $report, $range): array {
            return $days ??= $report->days($range);
        };

        return Inertia::render('admin/Report', [
            'range' => $range->toArray(),
            'usageChecked' => fn () => $byDay()['usageChecked'],
            'totals' => fn () => $byDay()['totals'],
            'days' => fn () => $byDay()['days'],
            'users' => fn () => $report->users($range),
            'images' => fn () => $report->images($range, $day, $user?->id),
            'filter' => ['day' => $day, 'user' => $user === null ? null : ['id' => $user->id, 'name' => $user->name]],
        ]);
    }

    private function withinMaxDays(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request): void {
            $to = $request->string('to')->toString();
            $range = ReportRange::of((string) $value, preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1 ? $to : null);

            if (count($range->dates()) > ReportRange::MAX_DAYS) {
                $fail('Pick a range of at most '.ReportRange::MAX_DAYS.' days.');
            }
        };
    }
}
