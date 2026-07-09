<?php

namespace App\Domain\Approval\Listeners;

use App\Domain\Approval\Events\ApprovalCompleted;
use App\Domain\Approval\Notifications\ApprovalCompletedNotification;
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
