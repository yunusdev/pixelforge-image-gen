<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenerationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GenerationController extends Controller
{
    /**
     * Generate. For now this only validates the prompt bar's settings;
     * saving and sending the generation to Pixelforge comes next (ticket 03).
     */
    public function store(StoreGenerationRequest $request): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'info', 'message' => 'Those settings are ready. Generating images is switched on soon.']);

        return to_route('create');
    }
}
