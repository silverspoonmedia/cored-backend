<?php

namespace App\Domain\Approval\Listeners;

use App\Domain\Approval\Events\ApprovalCompleted;
use App\Domain\Playground\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Update document status when approval completes.
 */
class UpdateDocumentStatus
{
    public function handle(ApprovalCompleted $event): void
    {
        $instance = $event->instance;

        \Log::info('🔔 UpdateDocumentStatus listener triggered', [
            'instance_id' => $instance->id,
            'document_type' => $instance->document_type,
            'status' => $instance->status,
        ]);

        // Currently only handles ORDER document type
        // Add more cases as needed for other document types
        match ($instance->document_type) {
            'ORDER' => $this->updateOrderStatus($instance),
            default => Log::info("Approval completed for unsupported document type: {$instance->document_type}"),
        };
    }

    protected function updateOrderStatus($instance): void
    {
        $order = Order::find($instance->document_id);

        if (! $order) {
            Log::warning('Order not found for approval instance', [
                'instance_id' => $instance->id,
                'order_id' => $instance->document_id,
            ]);

            return;
        }

        $newStatus = match ($instance->status) {
            'APPROVED' => 'completed',
            'REJECTED' => 'cancelled',
            default => null,
        };

        if ($newStatus && $order->status !== $newStatus) {
            $order->update(['status' => $newStatus]);

            Log::info('Order status updated from approval', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'old_status' => $order->status,
                'new_status' => $newStatus,
                'approval_instance_id' => $instance->id,
            ]);
        }
    }
}
