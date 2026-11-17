<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::fake(['pixelforge.test/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake();
});

function generate(array $settings)
{
    return test()->actingAs(User::factory()->create())
        ->from(route('create'))
        ->post(route('generations.store'), array_merge([
            'prompt' => 'A lighthouse on a cliff at dawn',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
        ], $settings));
}

test('valid settings pass validation and return to the Create page', function () {
    generate(['reference_images' => ['https://example.com/a.png', 'http://example.com/b.png']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('create'));
});

test('an empty prompt is explained', function () {
    generate(['prompt' => '   '])
        ->assertSessionHasErrors(['prompt' => 'Write a prompt to generate.']);
});

test('a prompt over 4,000 characters is explained, counting Unicode characters', function () {
    generate(['prompt' => str_repeat('🎨', 4000)])->assertSessionHasNoErrors();

    generate(['prompt' => str_repeat('🎨', 4003)])
        ->assertSessionHasErrors(['prompt' => 'Prompt is 3 characters over the 4,000 limit.']);
});

test('more than 4 reference images is explained', function () {
    generate(['reference_images' => array_fill(0, 5, 'https://example.com/a.png')])
        ->assertSessionHasErrors(['reference_images' => 'Use at most 4 reference images. Remove 1.']);
});

test('a reference that is not an http(s) link is explained and marked', function () {
    generate(['reference_images' => ['https://example.com/a.png', 'ftp://example.com/b.png']])
        ->assertSessionHasErrors([
            'reference_images.1' => "Reference 2 isn't a full web link. Use one starting with https://",
        ]);
});

test('only the sizes and qualities Pixelforge offers are accepted', function () {
    generate(['size' => '512x512', 'quality' => 'ultra'])
        ->assertSessionHasErrors(['size', 'quality']);
});

test('guests cannot generate', function () {
    $this->post(route('generations.store'), ['prompt' => 'x'])->assertRedirect(route('login'));
});
