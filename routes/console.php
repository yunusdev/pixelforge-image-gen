<?php

use App\Generations\MissedWebhookSweep;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('generations:check-missed', function (MissedWebhookSweep $sweep) {
    $this->info("Checked {$sweep->run()} generation(s) with Pixelforge.");
})->purpose('Fetch generations unfinished for over 2 minutes from Pixelforge, in case their webhook was missed');

Artisan::command('users:make-admin {email}', function (string $email) {
    $user = User::query()->where('email', $email)->first();

    if ($user === null) {
        $this->error("No user with the email {$email}.");

        return 1;
    }

    $user->forceFill(['is_admin' => true])->save();
    $this->info("{$email} is now an admin.");

    return 0;
})->purpose('Make the user with this email an admin, so they can open the Admin report');

Schedule::command('generations:check-missed')->everyMinute()->withoutOverlapping();
