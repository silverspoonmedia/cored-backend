<?php

namespace App\Domain\ActivityLog\Support;

use App\Domain\Identity\Events\LoginFailed;
use App\Domain\Identity\Events\LoginSuccessful;
use App\Domain\Identity\Events\PasswordChanged;
use App\Domain\Identity\Events\TwoFactorChallengeIssued;
use App\Domain\Identity\Events\TwoFactorDisabled;
use App\Domain\Identity\Events\TwoFactorEnabled;
use App\Domain\Identity\Events\TwoFactorLoginFailed;
use App\Domain\Identity\Events\UserLoggedOut;
use App\Domain\Identity\Listeners\LogAuthenticationEvents;
use App\Domain\Identity\Models\User;
use App\Support\ActivityLog\AuthActivityRecorder as AuthActivityRecorderContract;

/**
 * Writes authentication lifecycle entries to Spatie activitylog (`log_name: auth`).
 *
 * Complements {@see LogAuthenticationEvents} which writes
 * to Laravel's file log — both subscribe to the same auth domain events (the
 * audit package subscribes this class in `AuditServiceProvider`).
 *
 * Implements the kernel contract `App\Support\ActivityLog\AuthActivityRecorder`,
 * so Identity (kernel) writes the trail without importing this package. The
 * contract speaks ids; the subject and causer are resolved here.
 */
class AuthActivityRecorder implements AuthActivityRecorderContract
{
    public function __construct(
        private readonly ActivityLogRealtime $realtime,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        ?User $subject = null,
        ?User $causer = null,
        array $properties = [],
    ): void {
        $logger = activity()
            ->useLog('auth')
            ->event($event)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        $logger->log($event);

        $this->realtime->appended('auth', $event);
    }

    public function recordLogin(string $userId, ?string $ipAddress, ?string $userAgent, string $loginMethod): void
    {
        $user = $this->user($userId);

        if ($user === null) {
            return;
        }

        $this->record('login', $user, $user, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_method' => $loginMethod,
        ]);
    }

    public function recordLoginFailed(string $email, ?string $ipAddress, ?string $userAgent): void
    {
        $subject = User::query()->where('email', $email)->first();

        $this->record('login_failed', $subject, null, [
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'reason' => 'invalid_credentials',
        ]);
    }

    public function recordLogout(string $userId, ?string $ipAddress): void
    {
        $user = $this->user($userId);

        if ($user === null) {
            return;
        }

        $this->record('logout', $user, $user, [
            'ip_address' => $ipAddress,
        ]);
    }

    public function recordTwoFactorChallenge(string $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->user($userId);

        if ($user === null) {
            return;
        }

        $this->record('two_factor_challenge', $user, $user, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function recordTwoFactorLoginFailed(
        string $ipAddress,
        ?string $userAgent,
        string $reason,
        ?string $userId = null,
    ): void {
        $user = $userId !== null ? $this->user($userId) : null;

        $this->record('two_factor_login_failed', $user, null, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'reason' => $reason,
            'email' => $user?->email,
        ]);
    }

    public function recordPasswordChanged(string $userId, ?string $ipAddress, string $method): void
    {
        $user = $this->user($userId);

        if ($user === null) {
            return;
        }

        $this->record('password_changed', $user, $user, [
            'ip_address' => $ipAddress,
            'method' => $method,
        ]);
    }

    public function recordTwoFactorEnabled(string $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->user($userId);

        if ($user === null) {
            return;
        }

        $this->record('two_factor_enabled', $user, $user, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function recordTwoFactorDisabled(string $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->user($userId);

        if ($user === null) {
            return;
        }

        $this->record('two_factor_disabled', $user, $user, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    private function user(string $userId): ?User
    {
        return User::query()->find($userId);
    }

    /**
     * Subscriber registration: this recorder, not the kernel listener, is the
     * activity-log half of the auth trail. Subscribing here keeps the trail a
     * package concern — a host without `silverspoonmedia/audit` simply has one
     * listener per auth event instead of two.
     *
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

    public function handleLoginSuccessful(LoginSuccessful $event): void
    {
        $this->recordLogin(
            (string) $event->user->getKey(),
            $event->ipAddress,
            $event->userAgent,
            $event->loginMethod,
        );
    }

    public function handleLoginFailed(LoginFailed $event): void
    {
        $this->recordLoginFailed($event->email, $event->ipAddress, $event->userAgent);
    }

    public function handleUserLoggedOut(UserLoggedOut $event): void
    {
        $this->recordLogout((string) $event->user->getKey(), $event->ipAddress);
    }

    public function handleTwoFactorChallengeIssued(TwoFactorChallengeIssued $event): void
    {
        $this->recordTwoFactorChallenge(
            (string) $event->user->getKey(),
            $event->ipAddress,
            $event->userAgent,
        );
    }

    public function handleTwoFactorLoginFailed(TwoFactorLoginFailed $event): void
    {
        $this->recordTwoFactorLoginFailed(
            $event->ipAddress,
            $event->userAgent,
            $event->reason,
            $event->user !== null ? (string) $event->user->getKey() : null,
        );
    }

    public function handlePasswordChanged(PasswordChanged $event): void
    {
        $this->recordPasswordChanged(
            (string) $event->user->getKey(),
            $event->ipAddress,
            $event->method,
        );
    }

    public function handleTwoFactorEnabled(TwoFactorEnabled $event): void
    {
        $this->recordTwoFactorEnabled(
            (string) $event->user->getKey(),
            $event->ipAddress,
            $event->userAgent,
        );
    }

    public function handleTwoFactorDisabled(TwoFactorDisabled $event): void
    {
        $this->recordTwoFactorDisabled(
            (string) $event->user->getKey(),
            $event->ipAddress,
            $event->userAgent,
        );
    }
}
