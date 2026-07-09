<?php

namespace App\Domain\Approval\Notifications;

use App\Domain\Approval\Models\ApprovalInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ApprovalInstance $instance
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->instance->status;
        $url = route('approvals.show', $this->instance->id);

        return (new MailMessage)
            ->subject(__('approval.completed_subject', [
                'status' => $status,
            ]))
            ->line(__('approval.completed_line', [
                'document_type' => $this->instance->document_type,
                'document_id' => $this->instance->document_id,
                'status' => $status,
            ]))
            ->action(__('approval.notification_action_view'), $url);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'instance_id' => $this->instance->id,
            'status' => $this->instance->status,
            'type' => 'completed',
        ];
    }
}
