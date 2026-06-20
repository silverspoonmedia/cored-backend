<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_type',
        'name',
        'description',
        'version',
        'is_active',
        'locked_reason',
        'superseded_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'superseded_by');
    }

    public function supersedes(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class, 'superseded_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ApprovalInstance::class, 'workflow_id');
    }
}
