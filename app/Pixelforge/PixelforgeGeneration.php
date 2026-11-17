<?php

namespace App\Pixelforge;

use App\Pricing\Rates;
use App\Pricing\Usage;
use Carbon\CarbonImmutable;

/**
 * A snapshot of a generation as Pixelforge reports it (create reply, retrieve, or a webhook's data.object).
 */
final readonly class PixelforgeGeneration
{
    /** The metadata key carrying our local generation UUID, echoed in every webhook. */
    public const UUID_METADATA_KEY = 'generation_uuid';

    public function __construct(
        public string $id,
        /** queued | processing | succeeded | failed */
        public string $status,
        public ?string $generationUuid,
        public ?Rates $rates,
        public ?Usage $usage,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /** @param  array<string, mixed>  $generation */
    public static function fromArray(array $generation): self
    {
        $usage = $generation['usage'] ?? null;
        $error = $generation['error'] ?? null;
        $time = fn (string $key): ?CarbonImmutable => isset($generation[$key]) ? CarbonImmutable::parse($generation[$key])->utc() : null;

        return new self(
            id: (string) $generation['id'],
            status: (string) $generation['status'],
            generationUuid: $generation['metadata'][self::UUID_METADATA_KEY] ?? null,
            rates: isset($generation['rates']) ? Rates::fromUsdPerMillionTokens($generation['rates']) : null,
            usage: is_array($usage) ? new Usage(
                (int) $usage['input_text_tokens'],
                (int) $usage['input_image_tokens'],
                (int) $usage['output_tokens'],
                (int) $usage['reasoning_tokens'],
            ) : null,
            imageUrl: $generation['image_url'] ?? null,
            imageUrlExpiresAt: $time('image_url_expires_at'),
            errorCode: is_array($error) ? ($error['code'] ?? null) : null,
            errorMessage: is_array($error) ? ($error['message'] ?? null) : null,
            startedAt: $time('started_at'),
            completedAt: $time('completed_at'),
        );
    }
}
