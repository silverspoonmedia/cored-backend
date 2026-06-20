<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    use ConsumesApi;

    /**
     * Show my pending approvals.
     */
    public function myPending(Request $request): View
    {
        $response = $this->api()->get('approvals/my-pending');

        return view('adminlte4.approvals.my-pending', [
            'approvals' => $response->json('data', []),
        ]);
    }

    /**
     * Show approval instance details.
     */
    public function show(Request $request, string $instanceId): View
    {
        $response = $this->api()->get("approvals/instances/{$instanceId}");

        return view('adminlte4.approvals.show', [
            'instance' => $response->json('data'),
        ]);
    }

    /**
     * Approve a slot (called from dynamic JS form on show page).
     */
    public function approve(Request $request, string $slotId): RedirectResponse
    {
        $response = $this->api()->post("approvals/slots/{$slotId}/approve", [
            'notes' => $request->input('notes'),
        ]);

        if (! $response->successful()) {
            return back()->with('error', $this->apiMessage($response));
        }

        return back()->with('success', $this->apiMessage($response, __('approval.approved_successfully')));
    }

    /**
     * Reject a slot (modal form — JSON-capable).
     */
    public function reject(Request $request, string $slotId): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("approvals/slots/{$slotId}/reject", [
            'notes' => $request->input('notes'),
        ]);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.rejected_successfully'))),
            stayOnPageOnSuccess: true,
        );
    }

    /**
     * Reassign a slot (modal form — JSON-capable).
     */
    public function reassign(Request $request, string $slotId): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("approvals/slots/{$slotId}/reassign", [
            'user_id' => $request->input('user_id'),
            'reason' => $request->input('reason'),
        ]);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.reassigned_successfully'))),
            stayOnPageOnSuccess: true,
        );
    }

    /**
     * Batch approve (form on my-pending page — JSON-capable).
     */
    public function batchApprove(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('approvals/slots/batch-approve', [
            'slot_ids' => $request->input('slot_ids', []),
            'notes' => $request->input('notes'),
        ]);

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', $this->apiMessage($response, __('approval.batch_approved'))),
            stayOnPageOnSuccess: true,
        );
    }
}