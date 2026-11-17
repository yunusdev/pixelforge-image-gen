<?php

use App\Http\Controllers\CreatePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('create')
    : redirect()->route('register'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('create', CreatePageController::class)->name('create');
    Route::inertia('credits', 'Credits')->name('credits');
});

require __DIR__.'/settings.php';
