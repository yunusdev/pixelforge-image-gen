<?php

namespace App\Http\Controllers;

use App\Credits\Affordability;
use App\Credits\CreditPack;
use App\Credits\Ledger;
use App\Credits\Spending;
use App\Models\Generation;
use App\Models\LedgerEntry;
use App\Pricing\Cents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The Credits page: available balance, spending chart, credit packs and the ledger. Amounts in nanodollars. */
class CreditsController extends Controller
{
    public const LEDGER_PAGE_SIZE = 20;

    public const SPENDING_DAYS = 30;

    public function show(Request $request): Response
    {
        $user = $request->user();
        $balance = (int) $user->fresh()?->balance;
        $setAside = Affordability::setAside($user);
        $spending = Spending::lastDays($user, self::SPENDING_DAYS);

        return Inertia::render('Credits', [
            'summary' => [
                'available' => $balance - $setAside,
                'balance' => $balance,
                'setAside' => $setAside,
                'running' => Generation::query()->whereBelongsTo($user)->unfinished()->count(),
            ],
            'spending' => [
                'days' => $spending,
                'total' => array_sum(array_column($spending, 'amount')),
            ],
            'packs' => array_map(fn (CreditPack $pack) => $pack->value, CreditPack::cases()),
            'ledger' => $user->ledgerEntries()
                ->with('generation:id,prompt')
                ->latest('id')
                ->paginate(self::LEDGER_PAGE_SIZE)
                ->withQueryString()
                ->through(fn (LedgerEntry $entry) => [
                    'id' => $entry->id,
                    'at' => $entry->created_at->toIso8601String(),
                    'label' => $entry->reason->label($entry->amount),
                    'prompt' => $entry->generation?->prompt,
                    'amount' => $entry->amount,
                ]),
        ]);
    }

    /** Buy a credit pack with one click (fake: no payment). */
    public function buyPack(Request $request, Ledger $ledger): RedirectResponse
    {
        $validated = $request->validate(['pack' => ['required', 'integer', Rule::enum(CreditPack::class)]]);
        $pack = CreditPack::from((int) $validated['pack']);
        $entry = $ledger->buyPack($request->user(), $pack);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Added %s. You now have %s available.', Cents::down($pack->nanodollars()), Cents::down(Affordability::availableBalance($request->user()))),
        ]);
        Inertia::flash('highlight', $entry->id);

        return to_route('credits');
    }
}
