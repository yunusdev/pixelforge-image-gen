<?php

use App\Pricing\GenerationSettings;
use App\Pricing\Pricing;
use App\Pricing\PricingRules;
use App\Pricing\Rates;
use App\Pricing\Usage;

/** Pixelforge's GET /v1/pricing response, as published in its docs. */
function pixelforgePricing(array $rates = []): array
{
    return [
        'object' => 'pricing',
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'rates' => array_merge([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ], $rates),
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}

function rules(array $rates = []): PricingRules
{
    return PricingRules::fromPixelforge(pixelforgePricing($rates));
}

test('exact cost: the docs worked example gives a maximum provider cost of $0.373875000 and a top charge of $0.467343750', function () {
    $estimate = Pricing::estimate(
        new GenerationSettings(str_repeat('a', 400), '1536x1024', 'high', 2),
        rules(),
    );

    expect($estimate->providerCostHigh)->toBe(373_875_000)
        ->and($estimate->high)->toBe(467_343_750);
});

test('the docs worked example gives a low end of $0.357781250 from the low text and thinking counts', function () {
    $estimate = Pricing::estimate(
        new GenerationSettings(str_repeat('a', 400), '1536x1024', 'high', 2),
        rules(),
    );

    expect($estimate->providerCostLow)->toBe(286_225_000)
        ->and($estimate->low)->toBe(357_781_250)
        ->and([$estimate->prompt->tokensLow, $estimate->prompt->tokensHigh])->toBe([85, 115])
        ->and($estimate->prompt->providerCostHigh)->toBe(575_000)
        ->and($estimate->referenceImages->providerCostHigh)->toBe(15_300_000)
        ->and($estimate->imageOutput->providerCostHigh)->toBe(258_000_000)
        ->and([$estimate->thinking->tokensLow, $estimate->thinking->tokensHigh])->toBe([1000, 8000])
        ->and($estimate->thinking->providerCostHigh)->toBe(100_000_000);
});

test('exact cost: the charge is the provider cost × 1.25 rounded up to the nanodollar', function () {
    expect(Pricing::charge(373_875_000))->toBe(467_343_750)
        ->and(Pricing::charge(12_501))->toBe(15_627)   // 15,626.25
        ->and(Pricing::charge(1))->toBe(2)             // 1.25
        ->and(Pricing::charge(4))->toBe(5)
        ->and(Pricing::charge(0))->toBe(0);
});

test('exact cost: the estimate low end rounds down and the high end rounds up', function () {
    // Rates with a third decimal make the markup leave fractions of a nanodollar.
    $estimate = Pricing::estimate(
        new GenerationSettings('abcd', '1024x1024', 'low', 1),
        rules(['input_text_tokens' => '5.001', 'input_image_tokens' => '10.001']),
    );

    expect($estimate->providerCostLow)->toBe(18_850_765)    // × 1.25 = 23,563,456.25
        ->and($estimate->low)->toBe(23_563_456)
        ->and($estimate->providerCostHigh)->toBe(25_110_767) // × 1.25 = 31,388,458.75
        ->and($estimate->high)->toBe(31_388_459);
});

test('text tokens count Unicode characters, not bytes', function () {
    // 8 emoji are 32 bytes but 8 characters: 2 text tokens, so 1 to 3 with ±15%.
    $emoji = Pricing::estimate(new GenerationSettings(str_repeat('🎨', 8), '1024x1024', 'low', 0), rules());

    expect([$emoji->prompt->tokensLow, $emoji->prompt->tokensHigh])->toBe([1, 3]);

    // 10 characters (12 bytes): 3 text tokens, so 2 to 4.
    $accented = Pricing::estimate(new GenerationSettings('café crème', '1024x1024', 'low', 0), rules());

    expect([$accented->prompt->tokensLow, $accented->prompt->tokensHigh])->toBe([2, 4]);
});

test('prompt text tokens round characters up to whole tokens, then spread ±15%', function (string $prompt, int $low, int $high) {
    $estimate = Pricing::estimate(new GenerationSettings($prompt, '1024x1024', 'low', 0), rules());

    expect([$estimate->prompt->tokensLow, $estimate->prompt->tokensHigh])->toBe([$low, $high]);
})->with([
    'empty' => ['', 0, 0],
    '1 character' => ['a', 0, 2],
    '5 characters' => ['abcde', 1, 3],
    '4,000 characters' => [str_repeat('x', 4000), 850, 1150],
]);

test('every size and quality pair prices its output and thinking from the pricing table', function (string $quality, string $size, int $outputTokens, int $high) {
    $estimate = Pricing::estimate(new GenerationSettings('', $size, $quality, 0), rules());

    expect($estimate->imageOutput->tokensHigh)->toBe($outputTokens)
        ->and($estimate->high)->toBe($high);
})->with([
    ['low', '1024x1024', 280, 21_812_500],
    ['low', '1536x1024', 420, 28_812_500],
    ['low', '1024x1536', 420, 28_812_500],
    ['medium', '1024x1024', 1100, 86_250_000],
    ['medium', '1536x1024', 1650, 113_750_000],
    ['medium', '1024x1536', 1650, 113_750_000],
    ['high', '1024x1024', 4300, 340_000_000],
    ['high', '1536x1024', 6450, 447_500_000],
    ['high', '1024x1536', 6450, 447_500_000],
]);

test('each reference image adds 765 image tokens, from none up to the maximum of 4', function (int $references, int $providerCostHigh) {
    $estimate = Pricing::estimate(
        new GenerationSettings(str_repeat('a', 400), '1536x1024', 'high', $references),
        rules(),
    );

    expect($estimate->referenceImages->tokensHigh)->toBe($references * 765)
        ->and($estimate->providerCostHigh)->toBe($providerCostHigh);
})->with([
    [0, 358_575_000],
    [1, 366_225_000],
    [2, 373_875_000],
    [3, 381_525_000],
    [4, 389_175_000],
]);

test('an unknown size or quality is refused', function () {
    Pricing::estimate(new GenerationSettings('a', '512x512', 'low', 0), rules());
})->throws(InvalidArgumentException::class);

test('exact cost: the provider cost is the real usage times the rates fixed on the generation', function () {
    $rates = Rates::fromUsdPerMillionTokens(pixelforgePricing()['rates']);

    expect(Pricing::providerCost(new Usage(115, 1530, 6450, 8000), $rates))->toBe(373_875_000);
});

test('exact cost: the actual charge is the provider cost plus markup when it is within the estimate', function () {
    $rates = Rates::fromUsdPerMillionTokens(pixelforgePricing()['rates']);

    $actual = Pricing::actualCharge(new Usage(100, 1530, 6450, 3000), $rates, 467_343_750);

    expect($actual->providerCost)->toBe(311_300_000)
        ->and($actual->charge)->toBe(389_125_000)
        ->and($actual->capped)->toBeFalse()
        ->and($actual->absorbed)->toBe(0);
});

test('exact cost: an actual charge exactly at the estimate top is not capped', function () {
    $rates = Rates::fromUsdPerMillionTokens(pixelforgePricing()['rates']);

    $actual = Pricing::actualCharge(new Usage(115, 1530, 6450, 8000), $rates, 467_343_750);

    expect($actual->charge)->toBe(467_343_750)
        ->and($actual->capped)->toBeFalse()
        ->and($actual->absorbed)->toBe(0);
});

test('exact cost: the actual charge is capped at the estimate top and the absorbed amount is returned', function () {
    // Pixelforge raised the thinking rate to $15.00 between the estimate and acceptance.
    $raised = Rates::fromUsdPerMillionTokens(pixelforgePricing(['reasoning_tokens' => '15.00'])['rates']);

    $actual = Pricing::actualCharge(new Usage(115, 1530, 6450, 8000), $raised, 467_343_750);

    expect($actual->providerCost)->toBe(393_875_000) // uncapped charge: 492,343,750
        ->and($actual->charge)->toBe(467_343_750)
        ->and($actual->capped)->toBeTrue()
        ->and($actual->absorbed)->toBe(25_000_000);
});

test('exact cost: the actual charge rounds up to the nanodollar', function () {
    $rates = Rates::fromUsdPerMillionTokens(pixelforgePricing(['reasoning_tokens' => '12.501'])['rates']);

    $actual = Pricing::actualCharge(new Usage(0, 0, 0, 1), $rates, 1_000_000);

    expect($actual->providerCost)->toBe(12_501)
        ->and($actual->charge)->toBe(15_627);
});

test('rates in dollars per 1M tokens become exact nanodollars per token', function () {
    $rates = Rates::fromUsdPerMillionTokens([
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10',
        'output_tokens' => '40.5',
        'reasoning_tokens' => '0.125',
    ]);

    expect($rates->toArray())->toBe([
        'input_text_tokens' => 5_000,
        'input_image_tokens' => 10_000,
        'output_tokens' => 40_500,
        'reasoning_tokens' => 125,
    ]);
});

test('a rate finer than a nanodollar per token is refused rather than rounded', function () {
    Rates::fromUsdPerMillionTokens([
        'input_text_tokens' => '5.0001',
        'input_image_tokens' => '1',
        'output_tokens' => '1',
        'reasoning_tokens' => '1',
    ]);
})->throws(InvalidArgumentException::class);
