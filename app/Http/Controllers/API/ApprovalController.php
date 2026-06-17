<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstanceStepApprover;
use App\Repositories\Contracts\ApprovalRepositoryInterface;
use App\Services\ApprovalService;
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
        $instance = \App\Models\ApprovalInstance::with([
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
                        'approvers' => $step->approvers->map(function ($approver) {
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
}
