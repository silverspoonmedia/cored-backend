<?php

namespace App\Listeners;

use App\Events\ApprovalCompleted;
use App\Notifications\Approval\ApprovalCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendApprovalCompletedNotification implements ShouldQueue
{
    public function handle(ApprovalCompleted $event): void
    {
        $instance = $event->instance;

        if (! $instance->submitter) {
            return;
        }

        $instance->submitter->notify(new ApprovalCompletedNotification($instance));
    }
}
