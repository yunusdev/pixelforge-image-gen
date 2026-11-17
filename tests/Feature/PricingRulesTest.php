<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function pricingBody(string $textRate = '5.00'): array
{
    return [
        'object' => 'pricing',
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'rates' => [
            'input_text_tokens' => $textRate,
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
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

test('the Create page gets the rates and token rules from Pixelforge, in nanodollars', function () {
    Http::fake(['pixelforge.test/pricing' => Http::response(pricingBody('6.25'))]);

    $this->actingAs(User::factory()->create())
        ->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pricing.rates.input_text_tokens', 6_250)
            ->where('pricing.rates.reasoning_tokens', 12_500)
            ->where('pricing.text_token_variance_ppm', 150_000)
            ->where('pricing.tokens_per_reference_image', 765)
            ->where('pricing.output_tokens.high.1536x1024', 6450)
            ->where('pricing.reasoning_tokens.medium.max', 2000));

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->url() === 'https://pixelforge.test/pricing'
        && ! $request->hasHeader('Authorization'));
});

test('the pricing is cached briefly and fetched again once it expires', function () {
    Http::fake(['pixelforge.test/pricing' => Http::sequence()
        ->push(pricingBody('5.00'))
        ->push(pricingBody('7.00'))]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page->where('pricing.rates.input_text_tokens', 5_000));
    $this->actingAs($user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page->where('pricing.rates.input_text_tokens', 5_000));
    Http::assertSentCount(1);

    $this->travel(6)->minutes();

    $this->actingAs($user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page->where('pricing.rates.input_text_tokens', 7_000));
    Http::assertSentCount(2);
});

test('when Pixelforge cannot be reached the last known pricing is used', function () {
    Http::fake(['pixelforge.test/pricing' => Http::sequence()
        ->push(pricingBody('5.00'))
        ->push(['error' => ['code' => 'overloaded']], 503)]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('create'));
    $this->travel(6)->minutes();

    $this->actingAs($user)->get(route('create'))
        ->assertInertia(fn (Assert $page) => $page->where('pricing.rates.input_text_tokens', 5_000));
});

test('without any pricing from Pixelforge the page still opens, with no pricing to estimate from', function () {
    Http::fake(['pixelforge.test/pricing' => Http::response('down', 503)]);

    $this->actingAs(User::factory()->create())
        ->get(route('create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pricing', null));
});
