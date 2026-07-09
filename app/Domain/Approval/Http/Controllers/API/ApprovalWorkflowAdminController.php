<?php

namespace App\Domain\Approval\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Domain\Approval\Models\ApprovalWorkflow;
use App\Domain\Approval\Models\ApprovalWorkflowStep;
use App\Domain\Approval\Models\ApprovalWorkflowStepApprover;
use App\Domain\Identity\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApprovalWorkflowAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $workflows = ApprovalWorkflow::withCount(['steps', 'instances'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $workflows->map(fn (ApprovalWorkflow $workflow) => $this->workflowPayload($workflow)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'document_type' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $workflow = DB::transaction(function () use ($data) {
            if (($data['is_active'] ?? true) === true) {
                ApprovalWorkflow::where('document_type', $data['document_type'])->update(['is_active' => false]);
            }

            return ApprovalWorkflow::create([
                'document_type' => strtoupper($data['document_type']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'version' => $data['version'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => __('approval.workflow_created'),
            'data' => $this->workflowPayload($workflow),
        ], 201);
    }

    public function show(string $workflow): JsonResponse
    {
        $this->authorizeAdmin();

        $workflow = ApprovalWorkflow::with(['steps.approvers', 'createdBy'])
            ->findOrFail($workflow);

        return response()->json([
            'data' => $this->workflowPayload($workflow->loadCount(['steps', 'instances']), includeSteps: true),
            'meta' => [
                'roles' => Role::query()->where('guard_name', 'api')->orderBy('name')->pluck('name')->values(),
                'parallel_modes' => ['UNANIMOUS', 'ANY_REJECT', 'MAJORITY_VOTE'],
                'reject_actions' => ['STOP', 'BACK_TO_STEP'],
            ],
        ]);
    }

    public function update(Request $request, string $workflow): JsonResponse
    {
        $this->authorizeAdmin();

        $workflow = ApprovalWorkflow::findOrFail($workflow);
        $data = $request->validate([
            'document_type' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($workflow, $data) {
            if (($data['is_active'] ?? false) === true) {
                ApprovalWorkflow::where('document_type', strtoupper($data['document_type']))
                    ->whereKeyNot($workflow->id)
                    ->update(['is_active' => false]);
            }

            $workflow->update([
                'document_type' => strtoupper($data['document_type']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'version' => $data['version'],
                'is_active' => $data['is_active'] ?? false,
            ]);
        });

        return response()->json([
            'message' => __('approval.workflow_updated'),
            'data' => $this->workflowPayload($workflow->fresh()),
        ]);
    }

    public function activate(string $workflow): JsonResponse
    {
        $this->authorizeAdmin();

        $workflow = ApprovalWorkflow::findOrFail($workflow);

        DB::transaction(function () use ($workflow) {
            ApprovalWorkflow::where('document_type', $workflow->document_type)->update(['is_active' => false]);
            $workflow->update(['is_active' => true]);
        });

        return response()->json(['message' => __('approval.workflow_activated')]);
    }

    public function deactivate(string $workflow): JsonResponse
    {
        $this->authorizeAdmin();

        ApprovalWorkflow::findOrFail($workflow)->update(['is_active' => false]);

        return response()->json(['message' => __('approval.workflow_deactivated')]);
    }

    public function storeStep(Request $request, string $workflow): JsonResponse
    {
        $this->authorizeAdmin();

        $workflow = ApprovalWorkflow::findOrFail($workflow);
        $data = $request->validate([
            'step_order' => ['required', 'integer', 'min:1', Rule::unique('approval_workflow_steps')->where('workflow_id', $workflow->id)],
            'step_name' => 'required|string|max:255',
            'condition_field' => 'nullable|string|max:255',
            'condition_op' => 'nullable|string|in:=,!=,>,>=,<,<=',
            'condition_value' => 'nullable|string|max:255',
            'parallel_mode' => 'required|in:UNANIMOUS,ANY_REJECT,MAJORITY_VOTE',
            'reject_action' => 'required|in:STOP,BACK_TO_STEP',
            'reject_target_step_order' => 'nullable|integer|min:1',
            'reject_reset_downstream' => 'boolean',
            'reject_set_doc_status' => 'nullable|string|max:100',
            'approve_set_doc_status' => 'nullable|string|max:100',
            'reminder_after_days' => 'required|integer|min:0',
            'escalate_after_days' => 'nullable|integer|min:0',
            'escalate_to_role' => 'nullable|string|max:255',
            'allow_reassignment' => 'boolean',
        ]);

        $step = $workflow->steps()->create([
            'step_order' => $data['step_order'],
            'step_name' => $data['step_name'],
            'condition' => $this->conditionPayload($data),
            'parallel_mode' => $data['parallel_mode'],
            'reject_action' => $data['reject_action'],
            'reject_target_step_order' => $data['reject_target_step_order'] ?? null,
            'reject_reset_downstream' => $data['reject_reset_downstream'] ?? true,
            'reject_set_doc_status' => $data['reject_set_doc_status'] ?? null,
            'approve_set_doc_status' => $data['approve_set_doc_status'] ?? null,
            'reminder_after_days' => $data['reminder_after_days'],
            'escalate_after_days' => $data['escalate_after_days'] ?? null,
            'escalate_to_role' => $data['escalate_to_role'] ?? null,
            'allow_reassignment' => $data['allow_reassignment'] ?? false,
        ]);

        return response()->json([
            'message' => __('approval.workflow_step_created'),
            'data' => $this->stepPayload($step->load('approvers')),
        ], 201);
    }

    public function destroyStep(string $workflow, string $step): JsonResponse
    {
        $this->authorizeAdmin();

        ApprovalWorkflowStep::where('workflow_id', $workflow)->findOrFail($step)->delete();

        return response()->json(['message' => __('approval.workflow_step_deleted')]);
    }

    public function storeApprover(Request $request, string $workflow, string $step): JsonResponse
    {
        $this->authorizeAdmin();

        $step = ApprovalWorkflowStep::where('workflow_id', $workflow)->findOrFail($step);
        $data = $request->validate([
            'required_role' => 'required|string|exists:roles,name',
            'slot_label' => 'required|string|max:255',
        ]);

        $approver = $step->approvers()->create($data);

        return response()->json([
            'message' => __('approval.workflow_approver_created'),
            'data' => $approver,
        ], 201);
    }

    public function destroyApprover(string $workflow, string $step, string $approver): JsonResponse
    {
        $this->authorizeAdmin();

        ApprovalWorkflowStepApprover::where('workflow_step_id', $step)->findOrFail($approver)->delete();

        return response()->json(['message' => __('approval.workflow_approver_deleted')]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Root', 'Admin']), 403);
    }

    private function conditionPayload(array $data): ?array
    {
        if (blank($data['condition_field'] ?? null)) {
            return null;
        }

        return [
            'field' => $data['condition_field'],
            'op' => $data['condition_op'] ?? '>=',
            'value' => is_numeric($data['condition_value'] ?? null)
                ? (float) $data['condition_value']
                : ($data['condition_value'] ?? null),
        ];
    }

    private function workflowPayload(ApprovalWorkflow $workflow, bool $includeSteps = false): array
    {
        $payload = [
            'id' => $workflow->id,
            'document_type' => $workflow->document_type,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'version' => $workflow->version,
            'is_active' => $workflow->is_active,
            'steps_count' => $workflow->steps_count ?? $workflow->steps()->count(),
            'instances_count' => $workflow->instances_count ?? $workflow->instances()->count(),
            'created_at' => $workflow->created_at,
        ];

        if ($includeSteps) {
            $payload['steps'] = $workflow->steps
                ->sortBy('step_order')
                ->map(fn (ApprovalWorkflowStep $step) => $this->stepPayload($step))
                ->values();
        }

        return $payload;
    }

    private function stepPayload(ApprovalWorkflowStep $step): array
    {
        return [
            'id' => $step->id,
            'step_order' => $step->step_order,
            'step_name' => $step->step_name,
            'condition' => $step->condition,
            'parallel_mode' => $step->parallel_mode,
            'reject_action' => $step->reject_action,
            'reject_target_step_order' => $step->reject_target_step_order,
            'reject_reset_downstream' => $step->reject_reset_downstream,
            'reject_set_doc_status' => $step->reject_set_doc_status,
            'approve_set_doc_status' => $step->approve_set_doc_status,
            'reminder_after_days' => $step->reminder_after_days,
            'escalate_after_days' => $step->escalate_after_days,
            'escalate_to_role' => $step->escalate_to_role,
            'allow_reassignment' => $step->allow_reassignment,
            'approvers' => $step->approvers->map(fn (ApprovalWorkflowStepApprover $approver) => [
                'id' => $approver->id,
                'required_role' => $approver->required_role,
                'slot_label' => $approver->slot_label,
            ])->values(),
        ];
    }
}
