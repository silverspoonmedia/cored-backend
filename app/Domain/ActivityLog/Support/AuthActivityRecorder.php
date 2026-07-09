<?php

namespace App\Domain\ActivityLog\Support;

use App\Domain\Identity\Listeners\LogAuthenticationEvents;
use App\Domain\Identity\Models\User;

/**
 * Writes authentication lifecycle entries to Spatie activitylog (`log_name: auth`).
 *
 * Complements {@see LogAuthenticationEvents} which writes
 * to Laravel's file log — both subscribe to the same auth domain events.
 */
class AuthActivityRecorder
{
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
    }

    public function recordLogin(User $user, string $ipAddress, ?string $userAgent, string $loginMethod): void
    {
        $this->record('login', $user, $user, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_method' => $loginMethod,
        ]);
    }

    public function recordLoginFailed(string $email, string $ipAddress, ?string $userAgent): void
    {
        $subject = User::query()->where('email', $email)->first();

        $this->record('login_failed', $subject, null, [
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'reason' => 'invalid_credentials',
        ]);
    }

    public function recordLogout(User $user, string $ipAddress): void
    {
        $this->record('logout', $user, $user, [
            'ip_address' => $ipAddress,
        ]);
    }

    public function recordTwoFactorChallenge(User $user, string $ipAddress, ?string $userAgent): void
    {
        $this->record('two_factor_challenge', $user, $user, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function recordTwoFactorLoginFailed(
        string $ipAddress,
        ?string $userAgent,
        string $reason,
        ?User $user = null,
    ): void {
        $this->record('two_factor_login_failed', $user, null, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'reason' => $reason,
            'email' => $user?->email,
        ]);
    }

    public function recordPasswordChanged(User $user, string $ipAddress, string $method): void
    {
        $this->record('password_changed', $user, $user, [
            'ip_address' => $ipAddress,
            'method' => $method,
        ]);
    }

    public function recordTwoFactorEnabled(User $user, string $ipAddress, ?string $userAgent): void
    {
        $this->record('two_factor_enabled', $user, $user, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function recordTwoFactorDisabled(User $user, string $ipAddress, ?string $userAgent): void
    {
        $this->record('two_factor_disabled', $user, $user, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
