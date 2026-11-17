<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('create')
    : redirect()->route('register'))->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('create', 'Create')->name('create');
    Route::inertia('credits', 'Credits')->name('credits');
});

require __DIR__.'/settings.php';
