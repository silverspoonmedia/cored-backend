<?php

namespace Tests\Domain\Product\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Auth\AuthService;
use App\Support\Product\Enums\ProductActivationState;
use Database\Seeders\FileHostingRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class RegisterProductActivationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private AuthService $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);
        $this->auth = app(AuthService::class);
    }

    public function test_register_creates_implicit_product_activations(): void
    {
        $this->auth->register([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $user = User::query()->where('email', 'jane@example.com')->first();

        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'FileHosting',
            'state' => ProductActivationState::Active->value,
            'source' => 'register_hook',
        ]);
    }

    public function test_register_does_not_abort_on_activation_failure(): void
    {
        config()->set('products', []);

        $payload = $this->auth->register([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotEmpty($payload['token']);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_user_can_use_file_hosting_after_register(): void
    {
        $payload = $this->auth->register([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $user = User::query()->where('email', 'jane@example.com')->first();
        $user->markEmailAsVerified();

        $this->withHeader('Authorization', 'Bearer '.$payload['token'])
            ->getJson('/api/v1/file-hosting/quota')
            ->assertOk();
    }
}
