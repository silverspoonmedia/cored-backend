<?php

namespace App\Domain\Microstock\Models;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Inference\Models\AiModelRateSnapshot;
use App\Domain\Inference\Models\InferenceProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'microstock_ai_usage_events';

    protected $fillable = [
        'user_id',
        'asset_metadata_id',
        'file_id',
        'folder_id',
        'analysis_run_id',
        'billing_source',
        'stage',
        'attempt_index',
        'alias_model',
        'resolved_model',
        'ai_model_rate_snapshot_id',
        'inference_provider_id',
        'provider_request_id',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'provider_cost_micro_usd',
        'billable_micro_usd',
        'billed_at',
        'consolidated_at',
        'consolidation_adjustment_micro_usd',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'provider_cost_micro_usd' => 'integer',
            'billable_micro_usd' => 'integer',
            'consolidation_adjustment_micro_usd' => 'integer',
            'attempt_index' => 'integer',
            'billed_at' => 'datetime',
            'consolidated_at' => 'datetime',
            'voided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AssetMetadata, $this>
     */
    public function assetMetadata(): BelongsTo
    {
        return $this->belongsTo(AssetMetadata::class, 'asset_metadata_id');
    }

    /**
     * @return BelongsTo<HostedFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(HostedFile::class, 'file_id');
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<AiModelRateSnapshot, $this>
     */
    public function aiModelRateSnapshot(): BelongsTo
    {
        return $this->belongsTo(AiModelRateSnapshot::class, 'ai_model_rate_snapshot_id');
    }

    /**
     * @return BelongsTo<InferenceProvider, $this>
     */
    public function inferenceProvider(): BelongsTo
    {
        return $this->belongsTo(InferenceProvider::class, 'inference_provider_id');
    }
}
