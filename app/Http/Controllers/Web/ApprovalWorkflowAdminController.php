<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
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

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('admin/approval-workflows', $request->all());

        return $this->respondFromApi(
            $request,
            $response,
            fn (Response $r): RedirectResponse => redirect()
                ->route('admin.approval-workflows.show', $r->json('data.id'))
                ->with('success', $this->apiMessage($r, __('approval.workflow_created'))),
        );
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

    public function update(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $response = $this->api()->put("admin/approval-workflows/{$workflow}", $request->all());

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()
                ->route('admin.approval-workflows.show', $workflow)
                ->with('success', $this->apiMessage($response, __('approval.workflow_updated'))),
            stayOnPageOnSuccess: true,
        );
    }

    public function activate(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("admin/approval-workflows/{$workflow}/activate");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.workflow_activated'))),
            stayOnPageOnSuccess: true,
        );
    }

    public function deactivate(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("admin/approval-workflows/{$workflow}/deactivate");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.workflow_deactivated'))),
            stayOnPageOnSuccess: true,
        );
    }

    public function storeStep(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("admin/approval-workflows/{$workflow}/steps", $request->all());

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.workflow_step_created'))),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroyStep(Request $request, string $workflow, string $step): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("admin/approval-workflows/{$workflow}/steps/{$step}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.workflow_step_deleted'))),
            stayOnPageOnSuccess: true,
        );
    }

    public function storeApprover(Request $request, string $workflow, string $step): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("admin/approval-workflows/{$workflow}/steps/{$step}/approvers", $request->all());

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.workflow_approver_created'))),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroyApprover(Request $request, string $workflow, string $step, string $approver): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete("admin/approval-workflows/{$workflow}/steps/{$step}/approvers/{$approver}");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.workflow_approver_deleted'))),
            stayOnPageOnSuccess: true,
        );
    }
}
