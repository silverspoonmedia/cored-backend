<?php

namespace App\Domain\Vtual\Notifications;

use App\Domain\Vtual\Models\CreatorManagementInvite;
use App\Support\SpaPublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * Email invite to link (or create) a vTuber profile under a management org.
 * Sync send (pattern parity with ManagementMemberInvited). All copy localized.
 */
class ManagementCreatorInvited extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CreatorManagementInvite $invite,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = SpaPublicUrl::to('/apps/vtual/invites/respond', [
            'token' => $this->invite->token,
            'kind' => 'creator',
        ]);
        $management = $this->invite->management?->name ?? '';

        return (new MailMessage)
            ->subject(Lang::get('api/vtual.mail.creator_invite_subject', ['management' => $management]))
            ->greeting(Lang::get('api/vtual.mail.invite_greeting'))
            ->line(Lang::get('api/vtual.mail.creator_invite_line', ['management' => $management]))
            ->action(Lang::get('api/vtual.mail.invite_action'), $url)
            ->line(Lang::get('api/vtual.mail.invite_expiry', ['date' => $this->invite->expires_at?->toDayDateTimeString() ?? '']));
    }
}
