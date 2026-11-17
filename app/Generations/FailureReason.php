<?php

namespace App\Generations;

/**
 * Why a generation failed, in plain words for the user. Keyed by the stored error code.
 */
final class FailureReason
{
    /** Our own code for "retries ran out while Pixelforge was busy". */
    public const TOO_BUSY = 'too_busy';

    /** @var array<string, array{title: string, body: string}> */
    private const REASONS = [
        'generation_failed' => ['title' => "Couldn't draw this prompt", 'body' => "Pixelforge couldn't produce an image for this prompt. Try rewording it."],
        'internal_error' => ['title' => 'Pixelforge had a problem', 'body' => "Something went wrong on Pixelforge's side. Trying again usually works."],
        'content_policy_violation' => ['title' => 'Prompt blocked', 'body' => 'Pixelforge rejected this prompt. Try rewording it.'],
        'validation_failed' => ['title' => 'Settings rejected', 'body' => "Pixelforge didn't accept these settings. Check the prompt and reference links, then try again."],
        'daily_limit_exceeded' => ['title' => 'Daily limit reached', 'body' => "Pixelforge's daily limit is used up. Try again after midnight UTC."],
        self::TOO_BUSY => ['title' => 'Pixelforge was too busy', 'body' => "We couldn't get a slot after 10 minutes of trying. Try again."],
    ];

    private const FALLBACK = ['title' => "Couldn't start", 'body' => "We couldn't send this to Pixelforge. Try again in a moment."];

    /** @return array{code: string, title: string, body: string} */
    public static function for(string $code): array
    {
        return ['code' => $code, ...(self::REASONS[$code] ?? self::FALLBACK)];
    }
}
