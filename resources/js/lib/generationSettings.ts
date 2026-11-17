import { promptCharacters } from '@/lib/pricing';

/** Pixelforge's limits for a generation (POST /v1/generations). */
export const MAX_PROMPT_CHARACTERS = 4000;
export const MAX_REFERENCE_IMAGES = 4;

export const SIZES = [
    { value: '1024x1024', name: 'Square', ratio: '1:1' },
    { value: '1536x1024', name: 'Landscape', ratio: '3:2' },
    { value: '1024x1536', name: 'Portrait', ratio: '2:3' },
] as const;

export const QUALITIES = ['low', 'medium', 'high'] as const;

export type Size = (typeof SIZES)[number]['value'];
export type Quality = (typeof QUALITIES)[number];

export type GenerationSettings = {
    prompt: string;
    size: Size;
    quality: Quality;
    /** As typed; blank rows are ignored. */
    referenceImages: string[];
};

export type Problem =
    | { field: 'prompt'; message: string }
    | { field: 'reference_images'; index?: number; message: string };

export function isWebLink(value: string): boolean {
    try {
        const url = new URL(value);

        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

/** The reference image URLs that will be sent: blank rows dropped, whitespace trimmed. */
export function filledReferenceImages(settings: GenerationSettings): string[] {
    return settings.referenceImages.map((r) => r.trim()).filter(Boolean);
}

/**
 * Everything that stops Generate, in the order the prompt bar explains them.
 * Messages match StoreGenerationRequest so the server says the same thing.
 */
export function problemsWith(settings: GenerationSettings): Problem[] {
    const problems: Problem[] = [];
    const characters = promptCharacters(settings.prompt);

    if (settings.prompt.trim() === '') {
        problems.push({
            field: 'prompt',
            message: 'Write a prompt to generate.',
        });
    } else if (characters > MAX_PROMPT_CHARACTERS) {
        problems.push({
            field: 'prompt',
            message: `Prompt is ${(characters - MAX_PROMPT_CHARACTERS).toLocaleString('en-US')} characters over the 4,000 limit.`,
        });
    }

    const filled = settings.referenceImages
        .map((value, index) => ({ value: value.trim(), index }))
        .filter((r) => r.value !== '');

    if (filled.length > MAX_REFERENCE_IMAGES) {
        problems.push({
            field: 'reference_images',
            message: `Use at most 4 reference images. Remove ${filled.length - MAX_REFERENCE_IMAGES}.`,
        });
    }

    for (const { value, index } of filled) {
        if (!isWebLink(value)) {
            problems.push({
                field: 'reference_images',
                index,
                message: `Reference ${index + 1} isn't a full web link. Use one starting with https://`,
            });
        }
    }

    return problems;
}
