<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Events\LoginFailed;
use App\Domain\Identity\Events\LoginSuccessful;
use App\Domain\Identity\Events\PasswordChanged;
use App\Domain\Identity\Events\TwoFactorChallengeIssued;
use App\Domain\Identity\Events\TwoFactorDisabled;
use App\Domain\Identity\Events\TwoFactorEnabled;
use App\Domain\Identity\Events\TwoFactorLoginFailed;
use App\Domain\Identity\Events\UserLoggedOut;
use App\Domain\ActivityLog\Support\AuthActivityRecorder;

/**
 * Persists authentication lifecycle events to Spatie activitylog (`auth` log).
 *
 * Runs synchronously so audit rows exist before the HTTP response completes.
 * Local/dev setups often use QUEUE_CONNECTION=database without a worker; queuing
 * auth audit listeners would leave jobs stuck and the Activity log UI empty.
 */
class RecordAuthenticationActivity
{
    public function __construct(
        private readonly AuthActivityRecorder $recorder,
    ) {}

    public function handleLoginSuccessful(LoginSuccessful $event): void
    {
        $this->recorder->recordLogin(
            $event->user,
            $event->ipAddress,
            $event->userAgent,
            $event->loginMethod,
        );
    }

    public function handleLoginFailed(LoginFailed $event): void
    {
        $this->recorder->recordLoginFailed(
            $event->email,
            $event->ipAddress,
            $event->userAgent,
        );
    }

    public function handleUserLoggedOut(UserLoggedOut $event): void
    {
        $this->recorder->recordLogout($event->user, $event->ipAddress);
    }

    public function handleTwoFactorChallengeIssued(TwoFactorChallengeIssued $event): void
    {
        $this->recorder->recordTwoFactorChallenge(
            $event->user,
            $event->ipAddress,
            $event->userAgent,
        );
    }

    public function handleTwoFactorLoginFailed(TwoFactorLoginFailed $event): void
    {
        $this->recorder->recordTwoFactorLoginFailed(
            $event->ipAddress,
            $event->userAgent,
            $event->reason,
            $event->user,
        );
    }

    public function handlePasswordChanged(PasswordChanged $event): void
    {
        $this->recorder->recordPasswordChanged(
            $event->user,
            $event->ipAddress,
            $event->method,
        );
    }

    public function handleTwoFactorEnabled(TwoFactorEnabled $event): void
    {
        $this->recorder->recordTwoFactorEnabled(
            $event->user,
            $event->ipAddress,
            $event->userAgent,
        );
    }

    public function handleTwoFactorDisabled(TwoFactorDisabled $event): void
    {
        $this->recorder->recordTwoFactorDisabled(
            $event->user,
            $event->ipAddress,
            $event->userAgent,
        );
    }

    /**
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            LoginSuccessful::class => 'handleLoginSuccessful',
            LoginFailed::class => 'handleLoginFailed',
            UserLoggedOut::class => 'handleUserLoggedOut',
            TwoFactorChallengeIssued::class => 'handleTwoFactorChallengeIssued',
            TwoFactorLoginFailed::class => 'handleTwoFactorLoginFailed',
            PasswordChanged::class => 'handlePasswordChanged',
            TwoFactorEnabled::class => 'handleTwoFactorEnabled',
            TwoFactorDisabled::class => 'handleTwoFactorDisabled',
        ];
    }
}
