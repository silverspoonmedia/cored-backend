<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalInstanceStep extends Model
{
    use HasUuids;

    protected $fillable = [
        'instance_id',
        'workflow_step_id',
        'step_order',
        'step_name',
        'status',
        'condition_evaluated',
        'sla_target_date',
        'activated_at',
        'first_reminder_sent_at',
        'escalated_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'condition_evaluated' => 'array',
            'step_order' => 'integer',
            'sla_target_date' => 'datetime',
            'activated_at' => 'datetime',
            'first_reminder_sent_at' => 'datetime',
            'escalated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'workflow_step_id');
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(ApprovalInstanceStepApprover::class, 'instance_step_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'instance_step_id');
    }
}
