<?php

namespace App\Domain\Approval\Notifications;

use App\Domain\Approval\Models\ApprovalInstanceStepApprover;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalEscalationNotification extends Notification
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
        $url = route('approvals.show', $instance->id);

        return (new MailMessage)
            ->subject(__('approval.escalation_subject'))
            ->line(__('approval.escalation_line', [
                'document_type' => $instance->document_type,
                'document_id' => $instance->document_id,
            ]))
            ->action(__('approval.notification_action_review'), $url);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'slot_id' => $this->slot->id,
            'instance_id' => $this->slot->instanceStep->instance_id,
            'type' => 'escalation',
        ];
    }
}
