<?php

namespace Tests\Feature\Http\Controllers\API\V1\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_protected_auth_routes_require_bearer_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => 'Bearer token is required.',
            ]);

        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => 'Bearer token is required.',
            ]);
    }

    public function test_protected_auth_routes_reject_invalid_bearer_token_without_redirect(): void
    {
        $headers = ['Authorization' => 'Bearer invalid-token'];

        $this->get('/api/v1/auth/me', $headers)
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => 'Token is invalid.',
            ]);

        $this->post('/api/v1/auth/logout', [], $headers)
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => 'Token is invalid.',
            ]);
    }

    public function test_register_login_me_and_logout_flow(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Auth User',
            'email' => 'auth@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['user', 'token', 'token_type', 'expires_in'],
            ]);

        $token = $register->json('data.token');

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'auth@example.com')
            ->assertJsonPath('data.roles.0', 'User');

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();
    }

    public function test_login_with_remember_me_returns_extended_expiry(): void
    {
        config(['jwt.remember_ttl' => 90]);

        User::factory()->create([
            'email' => 'remember@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'remember@example.com',
            'password' => 'password123',
            'remember_me' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.remember', true)
            ->assertJsonPath('data.expires_in', 90 * 60);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'auth@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'auth@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid email or password.',
            ]);
    }

    public function test_forgot_and_reset_password_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_email_verification_and_resend_flow(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('User');

        $verificationUrl = URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addHour(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Email verified successfully.',
            ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('data.token');

        $this->postJson('/api/v1/auth/email/verification-notification', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Email address is already verified.',
            ]);
    }

    public function test_admin_route_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $token = auth('api')->login($user);

        $this->getJson('/api/v1/admin/ping', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJson([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action.',
            ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $adminToken = auth('api')->login($admin);

        $this->getJson('/api/v1/admin/ping', [
            'Authorization' => 'Bearer '.$adminToken,
        ])
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Admin access granted.',
            ]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $user->assignRole('User');

        $token = auth('api')->login($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }
}
