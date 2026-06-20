<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalWorkflowAdminController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('admin/approval-workflows');

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return view('adminlte4.admin.approval-workflows.index', [
            'workflows' => $response->json('data', []),
        ]);
    }

    public function create(): View
    {
        return view('adminlte4.admin.approval-workflows.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $response = $this->api()->post('admin/approval-workflows', $request->validate([
            'document_type' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]));

        if (! $response->successful()) {
            return back()->withInput()->with('error', $this->apiMessage($response));
        }

        return redirect()
            ->route('admin.approval-workflows.show', $response->json('data.id'))
            ->with('success', $this->apiMessage($response, __('approval.workflow_created')));
    }

    public function show(string $workflow): View|RedirectResponse
    {
        $response = $this->api()->get("admin/approval-workflows/{$workflow}");

        if (! $response->successful()) {
            return redirect()->route('admin.approval-workflows.index')->with('error', $this->apiMessage($response));
        }

        return view('adminlte4.admin.approval-workflows.show', [
            'workflowData' => $response->json('data', []),
            'meta' => $response->json('meta', []),
        ]);
    }

    public function edit(string $workflow): View|RedirectResponse
    {
        $response = $this->api()->get("admin/approval-workflows/{$workflow}");

        if (! $response->successful()) {
            return redirect()->route('admin.approval-workflows.index')->with('error', $this->apiMessage($response));
        }

        return view('adminlte4.admin.approval-workflows.edit', [
            'workflowData' => $response->json('data', []),
        ]);
    }

    public function update(Request $request, string $workflow): RedirectResponse
    {
        $response = $this->api()->put("admin/approval-workflows/{$workflow}", [
            ...$request->validate([
                'document_type' => 'required|string|max:50',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'version' => 'required|integer|min:1',
            ]),
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! $response->successful()) {
            return back()->withInput()->with('error', $this->apiMessage($response));
        }

        return redirect()
            ->route('admin.approval-workflows.show', $workflow)
            ->with('success', $this->apiMessage($response, __('approval.workflow_updated')));
    }

    public function activate(string $workflow): RedirectResponse
    {
        $response = $this->api()->post("admin/approval-workflows/{$workflow}/activate");

        if (! $response->successful()) {
            return back()->with('error', $this->apiMessage($response));
        }

        return back()->with('success', $this->apiMessage($response, __('approval.workflow_activated')));
    }

    public function deactivate(string $workflow): RedirectResponse
    {
        $response = $this->api()->post("admin/approval-workflows/{$workflow}/deactivate");

        if (! $response->successful()) {
            return back()->with('error', $this->apiMessage($response));
        }

        return back()->with('success', $this->apiMessage($response, __('approval.workflow_deactivated')));
    }

    public function storeStep(Request $request, string $workflow): RedirectResponse
    {
        $response = $this->api()->post("admin/approval-workflows/{$workflow}/steps", [
            ...$request->validate([
                'step_order' => 'required|integer|min:1',
                'step_name' => 'required|string|max:255',
                'condition_field' => 'nullable|string|max:255',
                'condition_op' => 'nullable|string|in:=,!=,>,>=,<,<=',
                'condition_value' => 'nullable|string|max:255',
                'parallel_mode' => 'required|in:UNANIMOUS,ANY_REJECT,MAJORITY_VOTE',
                'reject_action' => 'required|in:STOP,BACK_TO_STEP',
                'reject_target_step_order' => 'nullable|integer|min:1',
                'reject_set_doc_status' => 'nullable|string|max:100',
                'approve_set_doc_status' => 'nullable|string|max:100',
                'reminder_after_days' => 'required|integer|min:0',
                'escalate_after_days' => 'nullable|integer|min:0',
                'escalate_to_role' => 'nullable|string|max:255',
            ]),
            'reject_reset_downstream' => $request->boolean('reject_reset_downstream', true),
            'allow_reassignment' => $request->boolean('allow_reassignment'),
        ]);

        if (! $response->successful()) {
            return back()->withInput()->with('error', $this->apiMessage($response));
        }

        return back()->with('success', $this->apiMessage($response, __('approval.workflow_step_created')));
    }

    public function destroyStep(string $workflow, string $step): RedirectResponse
    {
        $response = $this->api()->delete("admin/approval-workflows/{$workflow}/steps/{$step}");

        if (! $response->successful()) {
            return back()->with('error', $this->apiMessage($response));
        }

        return back()->with('success', $this->apiMessage($response, __('approval.workflow_step_deleted')));
    }

    public function storeApprover(Request $request, string $workflow, string $step): RedirectResponse
    {
        $response = $this->api()->post("admin/approval-workflows/{$workflow}/steps/{$step}/approvers", $request->validate([
            'required_role' => 'required|string',
            'slot_label' => 'required|string|max:255',
        ]));

        if (! $response->successful()) {
            return back()->withInput()->with('error', $this->apiMessage($response));
        }

        return back()->with('success', $this->apiMessage($response, __('approval.workflow_approver_created')));
    }

    public function destroyApprover(string $workflow, string $step, string $approver): RedirectResponse
    {
        $response = $this->api()->delete("admin/approval-workflows/{$workflow}/steps/{$step}/approvers/{$approver}");

        if (! $response->successful()) {
            return back()->with('error', $this->apiMessage($response));
        }

        return back()->with('success', $this->apiMessage($response, __('approval.workflow_approver_deleted')));
    }
}
