<?php

namespace App\Events;

use App\Models\ApprovalInstance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an approval instance completes (APPROVED or REJECTED).
 */
class ApprovalCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ApprovalInstance $instance
    ) {}
}
