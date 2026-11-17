<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The prompt bar's settings, within Pixelforge's limits. The messages match
 * resources/js/lib/generationSettings.ts, which explains the same problems
 * in the prompt bar before anything is sent.
 */
class StoreGenerationRequest extends FormRequest
{
    public const MAX_PROMPT_CHARACTERS = 4000;

    public const MAX_REFERENCE_IMAGES = 4;

    public const SIZES = ['1024x1024', '1536x1024', '1024x1536'];

    public const QUALITIES = ['low', 'medium', 'high'];

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'prompt' => ['bail', 'required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $over = mb_strlen((string) $value, 'UTF-8') - self::MAX_PROMPT_CHARACTERS;

                if ($over > 0) {
                    $fail('Prompt is '.number_format($over).' characters over the 4,000 limit.');
                }
            }],
            'size' => ['required', Rule::in(self::SIZES)],
            'quality' => ['required', Rule::in(self::QUALITIES)],
            'reference_images' => ['bail', 'present', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                $extra = count((array) $value) - self::MAX_REFERENCE_IMAGES;

                if ($extra > 0) {
                    $fail("Use at most 4 reference images. Remove {$extra}.");
                }
            }],
            'reference_images.*' => [function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! Str::isUrl($value, ['http', 'https'])) {
                    $number = (int) Str::afterLast($attribute, '.') + 1;
                    $fail("Reference {$number} isn't a full web link. Use one starting with https://");
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Write a prompt to generate.',
            'size.*' => 'Pick a size: square, landscape or portrait.',
            'quality.*' => 'Pick a quality: low, medium or high.',
        ];
    }
}
