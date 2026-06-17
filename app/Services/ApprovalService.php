<?php

namespace App\Services;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStepApprover;
use App\Models\ApprovalLog;
use App\Models\User;
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

            // Check parallel mode - might not be final rejection
            if ($workflowStep->parallel_mode === 'MAJORITY_VOTE') {
                $stepComplete = $this->evaluateStepCompletion($step);
                if (! $stepComplete) {
                    return; // Not enough votes yet
                }
            }

            // Step is rejected
            $this->repository->updateStepStatus($step->id, 'REJECTED');
            $this->repository->cancelRemainingSlots($step->id);

            // Handle reject action
            if ($workflowStep->reject_action === 'STOP') {
                $this->repository->updateInstanceStatus($step->instance_id, 'REJECTED');
            }
        });
    }

    /**
     * Check if user can act on a slot.
     */
    protected function canActOnSlot(User $user, ApprovalInstanceStepApprover $slot): bool
    {
        // Direct role match
        if ($user->hasRole($slot->required_role)) {
            return true;
        }

        // TODO: Check active delegation

        return false;
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
            return;
        }

        // Activate next step
        $this->repository->activateStep($nextStep->id);
    }
}
