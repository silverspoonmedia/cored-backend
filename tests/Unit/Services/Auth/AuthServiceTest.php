<?php

namespace Tests\Unit\Services\Auth;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->service = new AuthService(new UserRepository);
    }

    public function test_register_persists_user_assigns_role_and_returns_token(): void
    {
        $payload = $this->service->register([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $user = User::query()->where('email', 'jane@example.com')->first();
        $this->assertTrue($user->hasRole('user'));
        $this->assertNotEmpty($payload['token']);
        $this->assertSame('jane@example.com', $payload['user']['email']);
    }

    public function test_login_throws_when_credentials_are_invalid(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->expectException(InvalidCredentialsException::class);

        $this->service->login([
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = $this->service->login([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotEmpty($payload['token']);
        $this->assertSame('user@example.com', $payload['user']['email']);
    }
}
