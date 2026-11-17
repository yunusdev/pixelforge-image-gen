<?php

use App\Generations\MissedWebhookSweep;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('generations:check-missed', function (MissedWebhookSweep $sweep) {
    $this->info("Checked {$sweep->run()} generation(s) with Pixelforge.");
})->purpose('Fetch generations unfinished for over 2 minutes from Pixelforge, in case their webhook was missed');

Schedule::command('generations:check-missed')->everyMinute()->withoutOverlapping();
