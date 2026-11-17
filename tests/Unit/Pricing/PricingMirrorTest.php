<?php

use App\Pricing\Estimate;
use App\Pricing\GenerationSettings;
use App\Pricing\Pricing;
use App\Pricing\PricingRules;
use App\Pricing\TokenRange;
use Symfony\Component\Process\Process;

/**
 * The prompt bar estimates in TypeScript (resources/js/lib/pricing.ts) so it can update
 * as the user types; PHP is the source of truth. These tests run the TypeScript under
 * Node and pin both to the same numbers so they can't drift apart.
 *
 * @param  list<GenerationSettings>  $cases
 * @return list<array<string, mixed>>
 */
function typescriptEstimates(PricingRules $rules, array $cases): array
{
    $process = new Process(['node', '--experimental-strip-types', '--no-warnings', 'tests/js/estimate.mjs'], dirname(__DIR__, 3));
    $process->setInput(json_encode([
        'rules' => $rules->toArray(),
        'cases' => array_map(fn (GenerationSettings $s) => [
            'prompt' => $s->prompt,
            'size' => $s->size,
            'quality' => $s->quality,
            'referenceImageCount' => $s->referenceImageCount,
        ], $cases),
    ], JSON_THROW_ON_ERROR));
    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> */
function estimateAsArray(Estimate $estimate): array
{
    $line = fn (TokenRange $r) => [
        'tokensLow' => $r->tokensLow,
        'tokensHigh' => $r->tokensHigh,
        'providerCostLow' => $r->providerCostLow,
        'providerCostHigh' => $r->providerCostHigh,
    ];

    return [
        'low' => $estimate->low,
        'high' => $estimate->high,
        'providerCostLow' => $estimate->providerCostLow,
        'providerCostHigh' => $estimate->providerCostHigh,
        'prompt' => $line($estimate->prompt),
        'referenceImages' => $line($estimate->referenceImages),
        'imageOutput' => $line($estimate->imageOutput),
        'thinking' => $line($estimate->thinking),
    ];
}

test('exact cost: the prompt bar estimate gives the docs worked example numbers too', function () {
    $rules = PricingRules::fromPixelforge(pixelforgePricing());

    [$typescript] = typescriptEstimates($rules, [
        new GenerationSettings(str_repeat('a', 400), '1536x1024', 'high', 2),
    ]);

    expect($typescript['providerCostHigh'])->toBe(373_875_000)
        ->and($typescript['high'])->toBe(467_343_750)
        ->and($typescript['low'])->toBe(357_781_250);
});

test('exact cost: the prompt bar estimate rounds the low end down and the high end up, like PHP', function () {
    $rules = PricingRules::fromPixelforge(pixelforgePricing(['input_text_tokens' => '5.001', 'input_image_tokens' => '10.001']));

    [$typescript] = typescriptEstimates($rules, [new GenerationSettings('abcd', '1024x1024', 'low', 1)]);

    expect($typescript['low'])->toBe(23_563_456)
        ->and($typescript['high'])->toBe(31_388_459);
});

test('the prompt bar estimate matches PHP for every size, quality, reference count and kind of prompt', function () {
    $rules = PricingRules::fromPixelforge(pixelforgePricing(['reasoning_tokens' => '12.501']));
    $prompts = ['', 'a', 'café crème', str_repeat('🎨', 9), 'A lighthouse on a cliff at dawn, oil painting', str_repeat('x', 4000)];

    $cases = [];
    foreach (['low', 'medium', 'high'] as $quality) {
        foreach (['1024x1024', '1536x1024', '1024x1536'] as $size) {
            foreach (range(0, 4) as $references) {
                $cases[] = new GenerationSettings($prompts[count($cases) % count($prompts)], $size, $quality, $references);
            }
        }
    }

    $php = array_map(fn (GenerationSettings $s) => estimateAsArray(Pricing::estimate($s, $rules)), $cases);

    expect(typescriptEstimates($rules, $cases))->toBe($php);
});
