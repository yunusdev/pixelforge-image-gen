/**
 * The prompt bar's estimate: a mirror of App\Pricing\Pricing::estimate().
 *
 * PHP is the source of truth for anything recorded; this copy only lets the
 * estimate update as the user types. tests/Unit/Pricing/PricingMirrorTest.php runs
 * this file under Node and pins both to the same numbers so they can't drift.
 *
 * Money is integer nanodollars (ADR-0001). Every value stays far below
 * Number.MAX_SAFE_INTEGER, and every division is an exact integer division.
 * Keep this file free of imports and non-erasable TypeScript so Node can run it.
 */

/** App\Pricing\PricingRules::toArray(): rates in nanodollars per token. */
export type PricingRules = {
    rates: {
        input_text_tokens: number;
        input_image_tokens: number;
        output_tokens: number;
        reasoning_tokens: number;
    };
    characters_per_text_token: number;
    text_token_variance_ppm: number;
    tokens_per_reference_image: number;
    output_tokens: Record<string, Record<string, number>>;
    reasoning_tokens: Record<string, { min: number; max: number }>;
};

export type EstimateSettings = {
    prompt: string;
    size: string;
    quality: string;
    referenceImageCount: number;
};

/** One line of the itemised receipt. */
export type TokenRange = {
    tokensLow: number;
    tokensHigh: number;
    providerCostLow: number;
    providerCostHigh: number;
};

export type Estimate = {
    low: number;
    high: number;
    providerCostLow: number;
    providerCostHigh: number;
    prompt: TokenRange;
    referenceImages: TokenRange;
    imageOutput: TokenRange;
    thinking: TokenRange;
};

const VARIANCE_SCALE = 1_000_000;
const MARKUP_NUMERATOR = 125;
const MARKUP_DENOMINATOR = 100;

/** Floor division for non-negative integers, without float rounding. */
function floorDiv(dividend: number, divisor: number): number {
    return (dividend - (dividend % divisor)) / divisor;
}

function ceilDiv(dividend: number, divisor: number): number {
    return floorDiv(dividend + divisor - 1, divisor);
}

/** Pixelforge counts Unicode characters (code points), not UTF-16 units or bytes. */
export function promptCharacters(prompt: string): number {
    // Code points, like PHP's mb_strlen(): a string iterates by code point.
    let characters = 0;

    for (const _ of prompt) {
        characters++;
    }

    return characters;
}

/** The charge: provider cost plus the 25% markup, rounded up to the nanodollar. */
export function charge(providerCost: number): number {
    return ceilDiv(providerCost * MARKUP_NUMERATOR, MARKUP_DENOMINATOR);
}

/** A provider cost with the markup, rounded down (the estimate's low end). */
export function chargeRoundedDown(providerCost: number): number {
    return floorDiv(providerCost * MARKUP_NUMERATOR, MARKUP_DENOMINATOR);
}

function line(
    tokensLow: number,
    tokensHigh: number,
    nanodollarsPerToken: number,
): TokenRange {
    return {
        tokensLow,
        tokensHigh,
        providerCostLow: tokensLow * nanodollarsPerToken,
        providerCostHigh: tokensHigh * nanodollarsPerToken,
    };
}

export function estimate(
    settings: EstimateSettings,
    rules: PricingRules,
): Estimate {
    const output = rules.output_tokens[settings.quality]?.[settings.size];
    const reasoning = rules.reasoning_tokens[settings.quality];

    if (output === undefined || reasoning === undefined) {
        throw new Error(
            `Unknown quality and size: ${settings.quality} ${settings.size}`,
        );
    }

    const textTokens = ceilDiv(
        promptCharacters(settings.prompt),
        rules.characters_per_text_token,
    );
    const variance = rules.text_token_variance_ppm;
    const imageTokens =
        settings.referenceImageCount * rules.tokens_per_reference_image;

    const prompt = line(
        floorDiv(textTokens * (VARIANCE_SCALE - variance), VARIANCE_SCALE),
        ceilDiv(textTokens * (VARIANCE_SCALE + variance), VARIANCE_SCALE),
        rules.rates.input_text_tokens,
    );
    const referenceImages = line(
        imageTokens,
        imageTokens,
        rules.rates.input_image_tokens,
    );
    const imageOutput = line(output, output, rules.rates.output_tokens);
    const thinking = line(
        reasoning.min,
        reasoning.max,
        rules.rates.reasoning_tokens,
    );

    const lines = [prompt, referenceImages, imageOutput, thinking];
    const providerCostLow = lines.reduce((s, l) => s + l.providerCostLow, 0);
    const providerCostHigh = lines.reduce((s, l) => s + l.providerCostHigh, 0);

    return {
        low: chargeRoundedDown(providerCostLow),
        high: charge(providerCostHigh),
        providerCostLow,
        providerCostHigh,
        prompt,
        referenceImages,
        imageOutput,
        thinking,
    };
}
