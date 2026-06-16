<?php

namespace App\Listeners\Auth;

use App\Events\Auth\LoginFailed;
use App\Events\Auth\LoginSuccessful;
use App\Events\Auth\UserLoggedOut;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogAuthenticationEvents implements ShouldQueue
{
    public function handleLoginSuccessful(LoginSuccessful $event): void
    {
        Log::info('User logged in successfully', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    public function handleLoginFailed(LoginFailed $event): void
    {
        Log::warning('Login attempt failed', [
            'email' => $event->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    public function handleUserLoggedOut(UserLoggedOut $event): void
    {
        Log::info('User logged out', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $event->ipAddress,
        ]);
    }

    /**
     * Register event listener subscriptions.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            LoginSuccessful::class => 'handleLoginSuccessful',
            LoginFailed::class => 'handleLoginFailed',
            UserLoggedOut::class => 'handleUserLoggedOut',
        ];
    }
}
