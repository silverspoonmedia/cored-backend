<?php

namespace Tests\Domain\Vtual\Feature;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use Database\Seeders\FileHostingRoleSeeder;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class RegisterVtualActivationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);
        $this->seed(VtualRoleSeeder::class);
        config(['hcaptcha.enabled' => false]);
    }

    #[Test]
    public function register_activates_implicit_vtual(): void
    {
        $payload = [
            'name' => 'New User',
            'email' => 'new-vtual-'.uniqid('', true).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertSuccessful();

        $user = User::query()->where('email', $payload['email'])->first();
        $this->assertNotNull($user);

        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'Vtual',
            'state' => ProductActivationState::Active->value,
        ]);
    }
}
