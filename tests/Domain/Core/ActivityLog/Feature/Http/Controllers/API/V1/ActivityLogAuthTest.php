<?php

namespace Tests\Domain\Core\ActivityLog\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Auth\TwoFactorService;
use App\Support\Encryption\EncryptedEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ActivityLogAuthTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_successful_login_writes_exactly_one_auth_activity_row(): void
    {
        $user = User::factory()->create([
            'email' => 'single-login@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('User');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'single-login@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertSame(
            1,
            Activity::query()
                ->where('log_name', 'auth')
                ->where('event', 'login')
                ->where('subject_id', $user->id)
                ->count(),
            'Each login must produce exactly one activity log row (no duplicate subscriber registration).',
        );
    }

    public function test_successful_login_writes_auth_activity_log(): void
    {
        $user = User::factory()->create([
            'email' => 'auth-user@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('User');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'auth-user@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'login',
            'subject_id' => $user->id,
            'causer_id' => $user->id,
        ]);

        $activity = Activity::query()->where('event', 'login')->firstOrFail();
        $this->assertSame('password', $activity->getProperty('login_method'));
    }

    public function test_failed_login_writes_auth_activity_log_without_causer(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'known@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'login_failed',
        ]);

        $activity = Activity::query()->where('event', 'login_failed')->firstOrFail();
        $this->assertNull($activity->causer_id);

        // The attempted email is PII: stored as an envelope, decrypted for
        // authorized viewers (see ActivityLogPiiEncryptionTest).
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($activity->properties->get('email')));
    }

    public function test_logout_writes_auth_activity_log(): void
    {
        $user = $this->userWithRole('User');
        $token = auth('api')->login($user);

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'logout',
            'subject_id' => $user->id,
            'causer_id' => $user->id,
        ]);
    }

    public function test_two_factor_flow_writes_challenge_and_login_activity(): void
    {
        $google2fa = new Google2FA;

        $user = User::factory()->create([
            'email' => '2fa-user@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('User');

        $twoFactor = app(TwoFactorService::class);
        $setup = $twoFactor->startSetup($user);
        $secret = $setup['secret'];
        $twoFactor->confirmSetup($user, $google2fa->getCurrentOtp($secret));

        $challenge = $this->postJson('/api/v1/auth/login', [
            'email' => '2fa-user@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->json('data.two_factor_token');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'two_factor_challenge',
            'subject_id' => $user->id,
        ]);

        $code = $google2fa->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/two-factor/login', [
            'two_factor_token' => $challenge,
            'code' => $code,
        ])->assertOk();

        $login = Activity::query()
            ->where('event', 'login')
            ->where('subject_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('two_factor', $login->getProperty('login_method'));
    }

    public function test_password_change_writes_auth_activity_log(): void
    {
        $user = $this->userWithRole('User');
        $token = auth('api')->login($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'password_changed',
            'subject_id' => $user->id,
        ]);

        $activity = Activity::query()->where('event', 'password_changed')->firstOrFail();
        $this->assertSame('change', $activity->getProperty('method'));
    }

    public function test_admin_can_filter_activity_logs_by_auth_log_name(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User');
        $token = auth('api')->login($user);

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/activity-logs?log_name=auth')
            ->assertOk()
            ->assertJsonPath('data.0.log_name', 'auth')
            ->assertJsonPath('data.0.event', 'logout');

        $events = collect($this->getJson('/api/v1/activity-logs?log_name=auth')->json('data'))
            ->pluck('event')
            ->all();

        $this->assertContains('logout', $events);
        $this->assertNotEmpty($events);
    }

    public function test_two_factor_enable_and_disable_write_auth_activity_log(): void
    {
        $user = $this->userWithRole('User');
        $token = auth('api')->login($user);
        $headers = ['Authorization' => 'Bearer '.$token];

        $setup = $this->postJson('/api/v1/auth/two-factor/setup', [], $headers)
            ->assertOk()
            ->json('data');

        $code = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);

        $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => $code], $headers)
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'two_factor_enabled',
            'subject_id' => $user->id,
            'causer_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'auth',
            'event' => 'two_factor_disabled',
            'subject_id' => $user->id,
        ]);

        $disableCode = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);

        $this->postJson('/api/v1/auth/two-factor/disable', ['code' => $disableCode], $headers)
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'two_factor_disabled',
            'subject_id' => $user->id,
            'causer_id' => $user->id,
        ]);
    }

    public function test_two_factor_setup_start_does_not_write_enabled_activity(): void
    {
        $user = $this->userWithRole('User');
        $token = auth('api')->login($user);

        $this->postJson('/api/v1/auth/two-factor/setup', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'auth',
            'event' => 'two_factor_enabled',
            'subject_id' => $user->id,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
