<?php

namespace App\Domain\Inference\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelRateSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'model_card_id',
        'model_slug',
        'inference_provider_id',
        'input_per_million_usd',
        'output_per_million_usd',
        'rate_source',
        'captured_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_per_million_usd' => 'decimal:4',
            'output_per_million_usd' => 'decimal:4',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AiModelCard, $this>
     */
    public function modelCard(): BelongsTo
    {
        return $this->belongsTo(AiModelCard::class, 'model_card_id');
    }

    /**
     * @return BelongsTo<InferenceProvider, $this>
     */
    public function inferenceProvider(): BelongsTo
    {
        return $this->belongsTo(InferenceProvider::class, 'inference_provider_id');
    }
}
