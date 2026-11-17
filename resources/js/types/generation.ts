import type { GenerationStatus } from '@/components/StatusTag.vue';
import type { Quality, Size } from '@/lib/generationSettings';

/** A generation as App\Http\Resources\GenerationResource sends it. Amounts in nanodollars. */
export type Generation = {
    id: number;
    status: GenerationStatus;
    prompt: string;
    size: Size;
    quality: Quality;
    reference_images: string[];
    estimate_low: number;
    estimate_high: number;
    /** The actual charge once succeeded; 0 otherwise. */
    charged: number;
    capped: boolean;
    failure: { code: string; title: string; body: string } | null;
    /** Null once the image has expired. */
    image_url: string | null;
    /** Pixelforge's image URL has expired (24 hours after completion); show the placeholder. */
    image_expired: boolean;
    image_url_expires_at: string | null;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
};

export function isFinal(generation: Pick<Generation, 'status'>): boolean {
    return generation.status === 'succeeded' || generation.status === 'failed';
}

/** Expired as of the last load, or since then while the page stayed open. */
export function imageExpired(
    generation: Pick<Generation, 'image_expired' | 'image_url_expires_at'>,
    now: Date = new Date(),
): boolean {
    return (
        generation.image_expired ||
        (generation.image_url_expires_at !== null &&
            new Date(generation.image_url_expires_at) <= now)
    );
}

/** Height over width, from the size ("1536x1024" → 2/3). */
export function aspect(size: Size): number {
    const [width, height] = size.split('x').map(Number);

    return height / width;
}
