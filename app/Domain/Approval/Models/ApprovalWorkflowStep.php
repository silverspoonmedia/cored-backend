<?php

namespace App\Domain\Approval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflowStep extends Model
{
    use HasUuids;

    protected $fillable = [
        'workflow_id',
        'step_order',
        'step_name',
        'condition',
        'parallel_mode',
        'reject_action',
        'reject_target_step_order',
        'reject_reset_downstream',
        'reject_set_doc_status',
        'approve_set_doc_status',
        'reminder_after_days',
        'escalate_after_days',
        'escalate_to_role',
        'allow_reassignment',
    ];

    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'step_order' => 'integer',
            'reject_target_step_order' => 'integer',
            'reject_reset_downstream' => 'boolean',
            'reminder_after_days' => 'integer',
            'escalate_after_days' => 'integer',
            'allow_reassignment' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStepApprover::class, 'workflow_step_id');
    }

    public function instanceSteps(): HasMany
    {
        return $this->hasMany(ApprovalInstanceStep::class, 'workflow_step_id');
    }
}
