<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Web\ConsumesApi;
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
        try {
            $response = $this->apiClient()->get('/api/v1/approvals/my-pending');

            return view('adminlte4.approvals.my-pending', [
                'approvals' => $response['data'] ?? [],
            ]);
        } catch (\Exception $e) {
            return view('adminlte4.approvals.my-pending', [
                'approvals' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show approval instance details.
     */
    public function show(Request $request, string $instanceId): View
    {
        try {
            $response = $this->apiClient()->get("/api/v1/approvals/instances/{$instanceId}");

            return view('adminlte4.approvals.show', [
                'instance' => $response['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', __('approval.instance_not_found'));
        }
    }

    /**
     * Approve a slot.
     */
    public function approve(Request $request, string $slotId)
    {
        try {
            $this->apiClient()->post("/api/v1/approvals/slots/{$slotId}/approve", [
                'notes' => $request->input('notes'),
            ]);

            return back()->with('success', __('approval.approved_successfully'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject a slot.
     */
    public function reject(Request $request, string $slotId)
    {
        $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        try {
            $this->apiClient()->post("/api/v1/approvals/slots/{$slotId}/reject", [
                'notes' => $request->input('notes'),
            ]);

            return back()->with('success', __('approval.rejected_successfully'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
