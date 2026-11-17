<?php

use App\Http\Controllers\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Loaded without the web middleware group: no session, no cookies, no CSRF.
| Requests are authenticated by their Pixelforge signature instead.
*/
Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)->name('webhooks.pixelforge');
