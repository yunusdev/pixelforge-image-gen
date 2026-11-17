<?php

namespace App\Http\Resources;

use App\Generations\FailureReason;
use App\Generations\GenerationStatus;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation as the feed and side panel show it. Amounts in nanodollars.
 *
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images,
            'estimate_low' => $this->estimate_low,
            'estimate_high' => $this->estimate_high,
            'charged' => $this->charged(),
            'capped' => $this->capped,
            'failure' => $this->status === GenerationStatus::Failed
                ? FailureReason::for($this->error_code ?? 'internal_error')
                : null,
            'image_url' => $this->image_url,
            'image_url_expires_at' => $this->image_url_expires_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'started_at' => $this->started_at?->toIso8601ZuluString(),
            'completed_at' => $this->completed_at?->toIso8601ZuluString(),
        ];
    }
}
