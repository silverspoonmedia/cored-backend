<?php

namespace App\Domain\Vtual\Notifications;

use App\Domain\Vtual\Models\ManagementInvite;
use App\Support\SpaPublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * Email invite to join a vTual management org. Sync send (pattern parity with
 * ResetPasswordNotification). All copy localized — never hardcode English.
 */
class ManagementMemberInvited extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ManagementInvite $invite,
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
        $url = SpaPublicUrl::to('/apps/vtual/invites/respond', ['token' => $this->invite->token]);
        $management = $this->invite->management?->name ?? '';
        $role = $this->invite->role?->value ?? '';

        return (new MailMessage)
            ->subject(Lang::get('api/vtual.mail.invite_subject', ['management' => $management]))
            ->greeting(Lang::get('api/vtual.mail.invite_greeting'))
            ->line(Lang::get('api/vtual.mail.invite_line', ['management' => $management, 'role' => $role]))
            ->action(Lang::get('api/vtual.mail.invite_action'), $url)
            ->line(Lang::get('api/vtual.mail.invite_expiry', ['date' => $this->invite->expires_at?->toDayDateTimeString() ?? '']));
    }
}
