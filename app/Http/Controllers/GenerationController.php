<?php

namespace App\Http\Controllers;

use App\Credits\NotEnoughCredits;
use App\Generations\SubmitGenerations;
use App\Http\Requests\StoreGenerationRequest;
use App\Pricing\Cents;
use App\Pricing\PricingRulesSource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GenerationController extends Controller
{
    /** Generate: save the generation, send it in the background, and return at once. */
    public function store(StoreGenerationRequest $request, PricingRulesSource $pricingRules, SubmitGenerations $generations): RedirectResponse
    {
        $rules = $pricingRules->current();

        if ($rules === null) {
            return back()->withErrors(['prompt' => "Prices couldn't be loaded from Pixelforge. Try again in a moment."]);
        }

        /** @var array{prompt: string, size: string, quality: string, reference_images: array<int, string>} $settings */
        $settings = $request->safe()->only(['prompt', 'size', 'quality', 'reference_images']);

        try {
            $generation = $generations->submit($request->user(), $settings, $rules);
        } catch (NotEnoughCredits $notEnough) {
            return back()->withErrors(['prompt' => $notEnough->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Started. Up to '.Cents::up($generation->estimate_high).' set aside until it finishes.',
        ]);

        return to_route('create');
    }
}
