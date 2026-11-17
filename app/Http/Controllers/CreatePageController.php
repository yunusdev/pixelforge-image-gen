<?php

namespace App\Http\Controllers;

use App\Http\Resources\GenerationResource;
use App\Pricing\PricingRulesSource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreatePageController extends Controller
{
    /** The feed shows this many of the newest generations. */
    public const FEED_SIZE = 20;

    public function __invoke(Request $request, PricingRulesSource $pricingRules): Response
    {
        return Inertia::render('Create', [
            // The prompt bar mirrors the estimate in resources/js/lib/pricing.ts.
            'pricing' => fn () => $pricingRules->current()?->toArray(),
            // Polled on its own (only: ['generations']) while anything is still running.
            'generations' => fn () => GenerationResource::collection(
                $request->user()->generations()->latest('id')->limit(self::FEED_SIZE)->get(),
            )->resolve($request),
        ]);
    }
}
