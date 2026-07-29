<?php

namespace App\Domain\Microstock\Http\Resources;

use App\Domain\Microstock\Services\AnalysisStageService;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetMetadataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'filename' => $this->whenLoaded('file', fn () => $this->file?->name),
            'title' => $this->title,
            'keywords' => $this->keywords ?? [],
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status?->value,
            'stages' => app(AnalysisStageService::class)->stageSummary($this->resource),
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
