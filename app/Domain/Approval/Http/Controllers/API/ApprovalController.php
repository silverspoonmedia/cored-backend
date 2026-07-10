<?php

namespace App\Domain\Approval\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Domain\Approval\Models\ApprovalInstance;
use App\Domain\Approval\Models\ApprovalInstanceStepApprover;
use App\Domain\Identity\Models\User;
use App\Domain\Approval\Repositories\Contracts\ApprovalRepositoryInterface;
use App\Domain\Approval\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService,
        protected ApprovalRepositoryInterface $repository
    ) {}

    /**
     * Get my pending approvals.
     */
    public function myPending(Request $request): JsonResponse
    {
        $approvals = $this->repository->findPendingApprovals(auth()->id());

        return response()->json([
            'data' => $approvals->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'instance_id' => $slot->instanceStep->instance_id,
                    'document_type' => $slot->instanceStep->instance->document_type,
                    'document_id' => $slot->instanceStep->instance->document_id,
                    'step_name' => $slot->instanceStep->step_name,
                    'step_order' => $slot->instanceStep->step_order,
                    'slot_label' => $slot->slot_label,
                    'required_role' => $slot->required_role,
                    'submitted_by' => $slot->instanceStep->instance->submitter->name ?? null,
                    'submitted_at' => $slot->instanceStep->instance->created_at,
                    'sla_target_date' => $slot->instanceStep->sla_target_date,
                ];
            }),
        ]);
    }

    /**
     * Show approval instance details.
     */
    public function show(Request $request, string $instanceId): JsonResponse
    {
        $instance = ApprovalInstance::with([
            'workflow',
            'steps.approvers.assignedUser',
            'steps.workflowStep',
            'submitter',
            'logs.actor',
        ])->findOrFail($instanceId);

        return response()->json([
            'data' => [
                'id' => $instance->id,
                'document_type' => $instance->document_type,
                'document_id' => $instance->document_id,
                'status' => $instance->status,
                'workflow' => [
                    'id' => $instance->workflow->id,
                    'name' => $instance->workflow->name,
                    'version' => $instance->workflow_version,
                ],
                'submitted_by' => [
                    'id' => $instance->submitter->id,
                    'name' => $instance->submitter->name,
                ],
                'submitted_at' => $instance->created_at,
                'completed_at' => $instance->completed_at,
                'steps' => $instance->steps->map(function ($step) {
                    return [
                        'id' => $step->id,
                        'order' => $step->step_order,
                        'name' => $step->step_name,
                        'status' => $step->status,
                        'activated_at' => $step->activated_at,
                        'completed_at' => $step->completed_at,
                        'allow_reassignment' => $step->workflowStep->allow_reassignment,
                        'approvers' => $step->approvers->map(function ($approver) use ($step) {
                            $reassignmentCandidates = User::role($approver->required_role)
                                ->where('users.id', '!=', $approver->assigned_user_id)
                                ->get(['id', 'name'])
                                ->map(fn ($user) => [
                                    'id' => $user->id,
                                    'name' => $user->name,
                                ]);

                            return [
                                'id' => $approver->id,
                                'slot_label' => $approver->slot_label,
                                'status' => $approver->status,
                                'assigned_to' => $approver->assignedUser ? [
                                    'id' => $approver->assignedUser->id,
                                    'name' => $approver->assignedUser->name,
                                ] : null,
                                'actor' => $approver->actor ? [
                                    'id' => $approver->actor->id,
                                    'name' => $approver->actor->name,
                                ] : null,
                                'allow_reassignment' => $step->workflowStep->allow_reassignment,
                                'reassignment_candidates' => $reassignmentCandidates,
                                'acted_at' => $approver->acted_at,
                                'notes' => $approver->notes,
                            ];
                        }),
                    ];
                }),
                'logs' => $instance->logs->map(function ($log) {
                    return [
                        'action' => $log->action,
                        'actor' => $log->actor ? [
                            'id' => $log->actor->id,
                            'name' => $log->actor->name,
                        ] : null,
                        'notes' => $log->notes,
                        'created_at' => $log->created_at,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Approve a slot.
     */
    public function approve(Request $request, string $slotId): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $slot = ApprovalInstanceStepApprover::findOrFail($slotId);

        try {
            $this->approvalService->approve($slot, $request->input('notes'));

            return response()->json([
                'message' => __('approval.approved_successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject a slot.
     */
    public function reject(Request $request, string $slotId): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $slot = ApprovalInstanceStepApprover::findOrFail($slotId);

        try {
            $this->approvalService->reject($slot, $request->input('notes'));

            return response()->json([
                'message' => __('approval.rejected_successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reassign(Request $request, string $slotId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'reason' => 'required|string|max:1000',
        ]);

        $slot = ApprovalInstanceStepApprover::findOrFail($slotId);
        $newApprover = User::findOrFail($request->string('user_id')->toString());

        try {
            $updatedSlot = $this->approvalService->reassign($slot, $newApprover, $request->string('reason')->toString());

            return response()->json([
                'message' => __('approval.reassigned_successfully'),
                'data' => [
                    'id' => $updatedSlot->id,
                    'assigned_to' => [
                        'id' => $updatedSlot->assignedUser?->id,
                        'name' => $updatedSlot->assignedUser?->name,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function batchApprove(Request $request): JsonResponse
    {
        $request->validate([
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'uuid|exists:approval_instance_step_approvers,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $slotIds = $request->input('slot_ids');
        $results = [];
        $successCount = 0;

        foreach ($slotIds as $slotId) {
            $slot = ApprovalInstanceStepApprover::find($slotId);

            try {
                $this->approvalService->approve($slot, $request->input('notes'));
                $results[] = ['id' => $slotId, 'status' => 'APPROVED'];
                $successCount++;
            } catch (\Exception $e) {
                $results[] = ['id' => $slotId, 'status' => 'FAILED', 'error' => $e->getMessage()];
            }
        }

        $message = __('approval.batch_approved', ['count' => $successCount]);

        return response()->json([
            'message' => $message,
            'data' => [
                'total' => count($slotIds),
                'success' => $successCount,
                'failed' => count($slotIds) - $successCount,
                'results' => $results,
            ],
        ]);
    }
}
