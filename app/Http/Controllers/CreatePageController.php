<?php

namespace App\Http\Controllers;

use App\Pricing\PricingRulesSource;
use Inertia\Inertia;
use Inertia\Response;

class CreatePageController extends Controller
{
    public function __invoke(PricingRulesSource $pricingRules): Response
    {
        return Inertia::render('Create', [
            // The prompt bar mirrors the estimate in resources/js/lib/pricing.ts.
            'pricing' => $pricingRules->current()?->toArray(),
        ]);
    }
}
