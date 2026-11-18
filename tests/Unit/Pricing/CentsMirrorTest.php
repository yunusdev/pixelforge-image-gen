<?php

use App\Pricing\Cents;
use Symfony\Component\Process\Process;

test('PHP and the browser round nanodollars to the same cents, up and down', function () {
    $amounts = [0, 1, 9_999_999, 10_000_000, 10_000_001, 467_343_750, 32_656_250, -1, -10_000_000, -32_656_250, 1_234_567_890_123];
    $process = new Process(['node', '--experimental-strip-types', '--no-warnings', 'tests/js/cents.mjs'], dirname(__DIR__, 3));
    $process->setInput(json_encode($amounts, JSON_THROW_ON_ERROR));
    $process->mustRun();

    $php = array_map(fn (int $n) => ['up' => $n < 0 ? null : Cents::up($n), 'down' => Cents::down($n)], $amounts);
    $typescript = array_map(fn (array $row, int $n) => ['up' => $n < 0 ? null : $row['up'], 'down' => $row['down']], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR), $amounts);

    expect($php)->toBe($typescript)
        ->and($php[5])->toBe(['up' => '$0.47', 'down' => '$0.46'])
        ->and($php[9])->toBe(['up' => null, 'down' => '-$0.04'])
        ->and($php[10])->toBe(['up' => '$1,234.57', 'down' => '$1,234.56']);
});
