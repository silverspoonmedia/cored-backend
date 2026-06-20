<?php

namespace App\Services;

use App\Events\ApprovalCompleted;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalInstanceStepApprover;
use App\Models\ApprovalLog;
use App\Models\User;
use App\Notifications\Approval\ApprovalRequestNotification;
use App\Repositories\Contracts\ApprovalRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(
        protected ApprovalRepositoryInterface $repository,
        protected ConditionEvaluator $conditionEvaluator
    ) {}

    /**
     * Initiate approval process for a document.
     */
    public function initiate(Model $document, string $documentType): ApprovalInstance
    {
        return DB::transaction(function () use ($document, $documentType) {
            $workflow = $this->repository->findActiveWorkflow($documentType);

            if (! $workflow) {
                throw new \Exception("No active workflow found for document type: {$documentType}");
            }

            // Validate: every step has eligible users
            foreach ($workflow->steps as $step) {
                $eligibleUsers = $this->repository->getUsersWithRole($step->approvers->first()->required_role);

                if ($eligibleUsers->isEmpty()) {
                    throw new \Exception(
                        "No users found for role [{$step->approvers->first()->required_role}] in step [{$step->step_name}]"
                    );
                }
            }

            // Create instance
            $instance = $this->repository->createInstance(
                $workflow,
                $document,
                auth()->id()
            );

            // Create instance steps and evaluate conditions
            foreach ($workflow->steps as $workflowStep) {
                $conditionMet = $this->conditionEvaluator->evaluate(
                    $workflowStep->condition,
                    $document
                );

                $instanceStep = $this->repository->createInstanceStep(
                    $instance->id,
                    $workflowStep->id,
                    $workflowStep->step_order,
                    $workflowStep->step_name,
                    $conditionMet ? 'WAITING' : 'SKIPPED',
                    [
                        'condition' => $workflowStep->condition,
                        'result' => $conditionMet,
                        'evaluated_at' => now()->toISOString(),
                    ]
                );

                if ($conditionMet) {
                    // Create approver slots
                    foreach ($workflowStep->approvers as $approverDef) {
                        $assignedUser = $this->repository->getUsersWithRole($approverDef->required_role)->first();

                        $this->repository->createApproverSlot(
                            $instanceStep->id,
                            $approverDef->id,
                            $approverDef->required_role,
                            $approverDef->slot_label,
                            $assignedUser->id
                        );
                    }
                }
            }

            // Activate first non-skipped step
            $this->activateNextStep($instance);

            // Log submission
            ApprovalLog::create([
                'instance_id' => $instance->id,
                'action' => 'SUBMITTED',
                'actor_id' => auth()->id(),
            ]);

            return $instance->load(['steps.approvers']);
        });
    }

    /**
     * Approve a slot.
     */
    public function approve(ApprovalInstanceStepApprover $slot, ?string $notes = null): void
    {
        DB::transaction(function () use ($slot, $notes) {
            $actor = auth()->user();

            if (! $this->canActOnSlot($actor, $slot)) {
                throw new \Exception(
                    "User does not have required role [{$slot->required_role}] or active delegation"
                );
            }

            if ($slot->status !== 'WAITING') {
                throw new \Exception(__('approval.slot_not_waiting'));
            }

            // Update slot
            $this->repository->updateSlotStatus(
                $slot->id,
                'APPROVED',
                $actor->id,
                $notes
            );

            // Log
            ApprovalLog::create([
                'instance_id' => $slot->instanceStep->instance_id,
                'instance_step_id' => $slot->instance_step_id,
                'action' => 'APPROVED',
                'actor_id' => $actor->id,
                'notes' => $notes,
            ]);

            // Evaluate step completion
            $stepComplete = $this->evaluateStepCompletion($slot->instanceStep->fresh());

            if ($stepComplete) {
                $this->repository->updateStepStatus($slot->instance_step_id, 'APPROVED');

                // Advance to next step or complete instance
                $this->activateNextStep($slot->instanceStep->instance->fresh());
            }
        });
    }

    /**
     * Reject a slot.
     */
    public function reject(ApprovalInstanceStepApprover $slot, string $notes): void
    {
        if (empty($notes)) {
            throw ValidationException::withMessages([
                'notes' => 'Rejection notes are required',
            ]);
        }

        DB::transaction(function () use ($slot, $notes) {
            $actor = auth()->user();

            if (! $this->canActOnSlot($actor, $slot)) {
                throw new \Exception(
                    "User does not have required role [{$slot->required_role}] or active delegation"
                );
            }

            // Update slot
            $this->repository->updateSlotStatus(
                $slot->id,
                'REJECTED',
                $actor->id,
                $notes
            );

            // Log
            ApprovalLog::create([
                'instance_id' => $slot->instanceStep->instance_id,
                'instance_step_id' => $slot->instance_step_id,
                'action' => 'REJECTED',
                'actor_id' => $actor->id,
                'notes' => $notes,
            ]);

            $step = $slot->instanceStep->fresh();
            $workflowStep = $step->workflowStep;

            if (! $this->evaluateStepCompletion($step)) {
                return;
            }

            $finalStatus = $this->resolveStepFinalStatus($step);
            $this->repository->updateStepStatus($step->id, $finalStatus);
            $this->repository->cancelRemainingSlots($step->id);

            if ($finalStatus === 'APPROVED') {
                $this->activateNextStep($step->instance->fresh());
                return;
            }

            if ($workflowStep->reject_action === 'BACK_TO_STEP') {
                $this->handleBackToStep($step);
                return;
            }

            $this->repository->updateInstanceStatus($step->instance_id, 'REJECTED');
            ApprovalCompleted::dispatch(ApprovalInstance::find($step->instance_id));
        });
    }

    /**
     * Check if user can act on a slot (direct role or active delegation).
     */
    protected function canActOnSlot(User $user, ApprovalInstanceStepApprover $slot): bool
    {
        if ($user->hasRole($slot->required_role)) {
            return true;
        }

        return ApprovalDelegation::where('delegate_id', $user->id)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where(function ($q) use ($slot) {
                $q->where('scope', 'ALL')
                  ->orWhere(fn ($q2) => $q2->where('scope', 'DOCUMENT_TYPE')
                                          ->where('document_type', $slot->instanceStep->instance->document_type))
                  ->orWhere(fn ($q3) => $q3->where('scope', 'INSTANCE')
                                          ->where('instance_id', $slot->instanceStep->instance_id));
            })
            ->exists();
    }

    /**
     * Evaluate if step is complete based on parallel mode.
     */
    protected function evaluateStepCompletion($step): bool
    {
        $workflowStep = $step->workflowStep;
        $totalSlots = $step->approvers()->count();
        $approvedCount = $step->approvers()->where('status', 'APPROVED')->count();
        $rejectedCount = $step->approvers()->where('status', 'REJECTED')->count();

        return match ($workflowStep->parallel_mode) {
            'UNANIMOUS' => $approvedCount === $totalSlots || $rejectedCount > 0,
            'ANY_REJECT' => $approvedCount === $totalSlots || $rejectedCount > 0,
            'MAJORITY_VOTE' => $this->evaluateMajorityVote($totalSlots, $approvedCount, $rejectedCount),
        };
    }

    /**
     * Evaluate majority vote threshold.
     */
    protected function evaluateMajorityVote(int $total, int $approved, int $rejected): bool
    {
        if ($total < 3) {
            throw new \LogicException('MAJORITY_VOTE requires at least 3 approvers');
        }

        $threshold = floor($total / 2) + 1;

        return $approved >= $threshold || $rejected >= $threshold;
    }

    /**
     * Determine whether a completed step is APPROVED or REJECTED.
     */
    protected function resolveStepFinalStatus(ApprovalInstanceStep $step): string
    {
        $workflowStep = $step->workflowStep;
        $approvedCount = $step->approvers()->where('status', 'APPROVED')->count();
        $rejectedCount = $step->approvers()->where('status', 'REJECTED')->count();

        if (in_array($workflowStep->parallel_mode, ['UNANIMOUS', 'ANY_REJECT'], true)) {
            return $rejectedCount > 0 ? 'REJECTED' : 'APPROVED';
        }

        $total = $step->approvers()->count();
        $threshold = floor($total / 2) + 1;

        return $approvedCount >= $threshold ? 'APPROVED' : 'REJECTED';
    }

    /**
     * Reset target step (and downstream if configured) after a BACK_TO_STEP rejection.
     */
    protected function handleBackToStep(ApprovalInstanceStep $rejectedStep): void
    {
        $instance = $rejectedStep->instance;
        $workflowStep = $rejectedStep->workflowStep;
        $targetOrder = $workflowStep->reject_target_step_order;

        $document = null;
        if ($instance->document_type === 'ORDER') {
            $document = \App\Models\Order::find($instance->document_id);
        }

        if ($targetOrder === null) {
            $this->repository->updateInstanceStatus($instance->id, 'REJECTED');

            if ($document && $workflowStep->reject_set_doc_status) {
                $document->update(['status' => $workflowStep->reject_set_doc_status]);
            }

            ApprovalCompleted::dispatch($instance->fresh());
            return;
        }

        $targetStep = $instance->steps()->where('step_order', $targetOrder)->firstOrFail();

        $targetStep->approvers()->update([
            'status' => 'WAITING',
            'actor_id' => null,
            'acted_at' => null,
            'notes' => null,
        ]);

        $targetStep->update([
            'status' => 'IN_PROGRESS',
            'activated_at' => now(),
            'completed_at' => null,
        ]);

        if ($workflowStep->reject_reset_downstream !== false) {
            $instance->steps()
                ->where('step_order', '>', $targetOrder)
                ->where('step_order', '<', $rejectedStep->step_order)
                ->where('status', 'APPROVED')
                ->each(function (ApprovalInstanceStep $step) {
                    $step->approvers()->update([
                        'status' => 'WAITING',
                        'actor_id' => null,
                        'acted_at' => null,
                        'notes' => null,
                    ]);

                    $step->update([
                        'status' => 'WAITING',
                        'activated_at' => null,
                        'completed_at' => null,
                    ]);
                });
        }

        ApprovalLog::create([
            'instance_id' => $instance->id,
            'instance_step_id' => $targetStep->id,
            'action' => 'REJECTED',
            'actor_id' => auth()->id(),
            'notes' => "Returned to step {$targetOrder} from step {$rejectedStep->step_order}",
            'metadata' => [
                'from_step' => $rejectedStep->step_order,
                'to_step' => $targetOrder,
                'reset_downstream' => $workflowStep->reject_reset_downstream !== false,
            ],
        ]);

        if ($document && $workflowStep->reject_set_doc_status) {
            $document->update(['status' => $workflowStep->reject_set_doc_status]);
        }
    }

    /**
     * Activate next waiting step or complete instance.
     */
    protected function activateNextStep(ApprovalInstance $instance): void
    {
        $currentStep = $this->repository->getActiveStepForInstance($instance->id);

        if ($currentStep) {
            return; // Already have active step
        }

        $nextStep = $this->repository->getNextWaitingStep($instance->id);

        if (! $nextStep) {
            // No more steps - instance complete
            $this->repository->updateInstanceStatus($instance->id, 'APPROVED');
            
            ApprovalCompleted::dispatch($instance->fresh());
            return;
        }

        $this->repository->activateStep($nextStep->id);

        foreach ($nextStep->fresh('approvers.assignedUser')->approvers as $approver) {
            if ($approver->assignedUser) {
                $approver->assignedUser->notify(new ApprovalRequestNotification($approver));
            }
        }
    }

    public function reassign(ApprovalInstanceStepApprover $slot, User $newApprover, string $reason): ApprovalInstanceStepApprover
    {
        return DB::transaction(function () use ($slot, $newApprover, $reason) {
            $actor = auth()->user();
            $slot = $slot->fresh(['instanceStep.workflowStep', 'assignedUser']);
            $step = $slot->instanceStep;
            $workflowStep = $step->workflowStep;

            if ($step->status !== 'IN_PROGRESS') {
                throw new \Exception(__('approval.reassignment_step_not_active'));
            }

            if ($slot->status !== 'WAITING') {
                throw new \Exception(__('approval.reassignment_slot_not_waiting'));
            }

            if ($workflowStep->allow_reassignment !== true) {
                throw new \Exception(__('approval.reassignment_not_allowed'));
            }

            if (! $actor || $slot->assigned_user_id !== $actor->id) {
                throw new \Exception(__('approval.reassignment_only_assignee'));
            }

            if (! $newApprover->hasRole($slot->required_role)) {
                throw new \Exception(__('approval.reassignment_same_role_required', [
                    'role' => $slot->required_role,
                ]));
            }

            if ($newApprover->id === $slot->assigned_user_id) {
                throw new \Exception(__('approval.reassignment_same_user'));
            }

            $oldUserId = $slot->assigned_user_id;

            $this->repository->reassignSlot(
                $slot->id,
                $newApprover->id,
                $actor->id,
                $reason
            );

            ApprovalLog::create([
                'instance_id' => $step->instance_id,
                'instance_step_id' => $step->id,
                'action' => 'REASSIGNED',
                'actor_id' => $actor->id,
                'target_user_id' => $newApprover->id,
                'notes' => $reason,
                'metadata' => [
                    'slot_id' => $slot->id,
                    'from_user_id' => $oldUserId,
                    'to_user_id' => $newApprover->id,
                    'required_role' => $slot->required_role,
                ],
            ]);

            $updatedSlot = $slot->fresh(['assignedUser']);

            if ($updatedSlot->assignedUser) {
                $updatedSlot->assignedUser->notify(new ApprovalRequestNotification($updatedSlot));
            }

            return $updatedSlot;
        });
    }
}
