<?php

namespace App\Domain\Approval\Models;

use App\Domain\Identity\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalInstance extends Model
{
    use HasUuids;

    protected $fillable = [
        'workflow_id',
        'workflow_version',
        'document_type',
        'document_id',
        'status',
        'submitted_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'workflow_version' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalInstanceStep::class, 'instance_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'instance_id');
    }
}
