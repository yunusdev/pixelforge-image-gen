<?php

use App\Http\Controllers\CreatePageController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\GenerationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('create')
    : redirect()->route('register'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('create', CreatePageController::class)->name('create');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::get('credits', [CreditsController::class, 'show'])->name('credits');
    Route::post('credits/packs', [CreditsController::class, 'buyPack'])->name('credits.packs.store');
});

require __DIR__.'/settings.php';
