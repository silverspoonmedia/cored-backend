<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
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
            $response = $this->api()->get('approvals/my-pending');

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
            $response = $this->api()->get("approvals/instances/{$instanceId}");

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
            $this->api()->post("approvals/slots/{$slotId}/approve", [
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
            $this->api()->post("approvals/slots/{$slotId}/reject", [
                'notes' => $request->input('notes'),
            ]);

            return back()->with('success', __('approval.rejected_successfully'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reassign(Request $request, string $slotId)
    {
        $request->validate([
            'user_id' => 'required|uuid',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $this->api()->post("approvals/slots/{$slotId}/reassign", [
                'user_id' => $request->input('user_id'),
                'reason' => $request->input('reason'),
            ]);

            return back()->with('success', __('approval.reassigned_successfully'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function batchApprove(Request $request)
    {
        $request->validate([
            'slot_ids' => 'required|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $response = $this->api()->post('approvals/slots/batch-approve', [
                'slot_ids' => $request->input('slot_ids'),
                'notes' => $request->input('notes'),
            ]);

            $data = $response['data'];

            if ($data['failed'] > 0) {
                return back()->with('warning', __('approval.batch_partial', [
                    'success' => $data['success'],
                    'failed' => $data['failed'],
                ]));
            }

            return back()->with('success', __('approval.batch_approved', ['count' => $data['success']]));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
