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
    image_url: string | null;
    image_url_expires_at: string | null;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
};

export function isFinal(generation: Pick<Generation, 'status'>): boolean {
    return generation.status === 'succeeded' || generation.status === 'failed';
}

/** Height over width, from the size ("1536x1024" → 2/3). */
export function aspect(size: Size): number {
    const [width, height] = size.split('x').map(Number);

    return height / width;
}
