<?php

namespace App\Http\Controllers;

use App\Generations\FeedFilter;
use App\Http\Resources\GenerationResource;
use App\Pricing\PricingRulesSource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreatePageController extends Controller
{
    /** The feed shows this many of the newest generations, and "Load 20 more" adds as many again. */
    public const FEED_SIZE = 20;

    /** The most the feed will show at once, so polling stays cheap. */
    public const FEED_MAX = 1000;

    public function __invoke(Request $request, PricingRulesSource $pricingRules): Response
    {
        $user = $request->user();
        $filter = FeedFilter::fromRequest($request->query('filter'));
        $shown = $this->shown($request->query('shown'));

        return Inertia::render('Create', [
            // The prompt bar mirrors the estimate in resources/js/lib/pricing.ts.
            'pricing' => fn () => $pricingRules->current()?->toArray(),
            // Polled on its own (only: ['generations', 'feed']) while anything is still running.
            'generations' => fn () => GenerationResource::collection(
                $filter->apply($user->generations())->latest('id')->limit($shown)->get(),
            )->resolve($request),
            'feed' => fn () => [
                'filter' => $filter->value,
                'shown' => $shown,
                'total' => $filter->apply($user->generations())->count(),
                'everything' => $user->generations()->count(),
            ],
            // Told to a new user in the empty feed. Nanodollars.
            'startingCredits' => (int) config('credits.sign_up_bonus'),
        ]);
    }

    /** How many to show: whole steps of 20, at least one step, at most FEED_MAX. */
    private function shown(mixed $asked): int
    {
        $asked = filter_var($asked, FILTER_VALIDATE_INT) ?: 0;
        $steps = max(1, (int) ceil($asked / self::FEED_SIZE));

        return min($steps * self::FEED_SIZE, self::FEED_MAX);
    }
}
