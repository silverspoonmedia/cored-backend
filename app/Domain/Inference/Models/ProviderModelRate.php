<?php

namespace App\Domain\Inference\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderModelRate extends Model
{
    use HasUuids;

    protected $table = 'ai_provider_model_rates';

    protected $fillable = [
        'inference_provider_id',
        'model_card_id',
        'input_per_million_usd',
        'output_per_million_usd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_per_million_usd' => 'decimal:4',
            'output_per_million_usd' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<InferenceProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(InferenceProvider::class, 'inference_provider_id');
    }

    /**
     * @return BelongsTo<AiModelCard, $this>
     */
    public function modelCard(): BelongsTo
    {
        return $this->belongsTo(AiModelCard::class, 'model_card_id');
    }
}
