<?php

namespace App\Pixelforge;

/**
 * Checks a Pixelforge-Signature header (`t=<unix>,v1=<hex>`): the hex HMAC-SHA256 of
 * `<t>.<raw body>` keyed with the webhook signing secret, compared in constant time,
 * with `t` no more than 300 seconds from now.
 */
final class WebhookSignature
{
    public const TOLERANCE_SECONDS = 300;

    public static function isValid(?string $header, string $rawBody, string $secret, int $now): bool
    {
        if ($secret === '' || $header === null || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === [] || abs($now - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, strtolower($signature))) {
                return true;
            }
        }

        return false;
    }
}
