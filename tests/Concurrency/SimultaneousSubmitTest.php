<?php

use App\Credits\Ledger;
use App\Credits\NotEnoughCredits;
use App\Generations\GenerationStatus;
use App\Generations\SubmitGenerations;
use App\Models\Generation;
use App\Models\User;
use App\Pricing\PricingRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

test('never twice: five submits at the same moment, from separate processes, with $0.50 available and a $0.47 top allow exactly one', function () {
    Queue::fake();
    // The $5.00 sign-up bonus, spent down to $0.50 by an earlier generation.
    $user = User::factory()->create();
    $spent = Generation::factory()->for($user)->status(GenerationStatus::Succeeded)->create(['actual_charge' => 4_500_000_000]);
    app(Ledger::class)->charge($spent);
    $settings = [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ];
    $rules = PricingRules::fromPixelforge(pixelforgePricing());
    $startAt = microtime(true) + 0.5;

    DB::disconnect();
    $children = [];

    foreach (range(1, 5) as $ignored) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            DB::purge();
            // Hold each check open a while, so all five are in flight together.
            Generation::creating(fn () => usleep(300_000));
            time_sleep_until($startAt);

            try {
                app(SubmitGenerations::class)->submit($user, $settings, $rules);
            } catch (NotEnoughCredits) {
            }

            DB::disconnect();
            // Leave without running the test runner's shutdown code in this copy of the process.
            posix_kill(getmypid(), SIGKILL);
        }

        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    expect(Generation::whereBelongsTo($user)->unfinished()->count())->toBe(1);
});
