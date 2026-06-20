<?php

namespace App\Notifications\Approval;

use App\Models\ApprovalInstanceStepApprover;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ApprovalInstanceStepApprover $slot
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $instance = $this->slot->instanceStep->instance;
        $documentType = $instance->document_type;
        $documentId = $instance->document_id;

        $url = route('approvals.show', $instance->id);

        return (new MailMessage)
            ->subject(__('approval.notification_request_subject', [
                'document_type' => $documentType,
            ]))
            ->line(__('approval.notification_request_line', [
                'document_type' => $documentType,
                'document_id' => $documentId,
            ]))
            ->action(__('approval.notification_action_review'), $url)
            ->line(__('approval.notification_thank_you'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'slot_id' => $this->slot->id,
            'instance_id' => $this->slot->instanceStep->instance_id,
            'document_type' => $this->slot->instanceStep->instance->document_type,
            'document_id' => $this->slot->instanceStep->instance->document_id,
            'step_name' => $this->slot->instanceStep->step_name,
        ];
    }
}
