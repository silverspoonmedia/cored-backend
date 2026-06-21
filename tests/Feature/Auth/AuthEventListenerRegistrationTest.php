<?php

namespace Tests\Feature\Auth;

use App\Events\Auth\LoginFailed;
use App\Events\Auth\LoginSuccessful;
use App\Events\Auth\PasswordChanged;
use App\Events\Auth\TwoFactorChallengeIssued;
use App\Events\Auth\TwoFactorDisabled;
use App\Events\Auth\TwoFactorEnabled;
use App\Events\Auth\TwoFactorLoginFailed;
use App\Events\Auth\UserLoggedOut;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards against duplicate Event::subscribe() + auto-discovery double-registration.
 */
class AuthEventListenerRegistrationTest extends TestCase
{
    /**
     * Each auth event should have exactly two listeners: file log + activity log.
     *
     * @return array<string, int>
     */
    public static function authEventsProvider(): array
    {
        return [
            'login successful' => [LoginSuccessful::class, 2],
            'login failed' => [LoginFailed::class, 2],
            'logout' => [UserLoggedOut::class, 2],
            'two factor challenge' => [TwoFactorChallengeIssued::class, 2],
            'two factor login failed' => [TwoFactorLoginFailed::class, 2],
            'password changed' => [PasswordChanged::class, 2],
            'two factor enabled' => [TwoFactorEnabled::class, 2],
            'two factor disabled' => [TwoFactorDisabled::class, 2],
        ];
    }

    #[DataProvider('authEventsProvider')]
    public function test_auth_event_has_expected_listener_count(string $event, int $expected): void
    {
        $this->assertCount(
            $expected,
            Event::getListeners($event),
            "Duplicate listener registration detected for {$event}. "
            .'Expected '.$expected.' (LogAuthenticationEvents + RecordAuthenticationActivity). '
            .'Check AppServiceProvider for manual Event::subscribe() on auth listeners.',
        );
    }
}
