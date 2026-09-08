<?php

namespace Tests\Unit\Support\Auth;

use App\Domain\Identity\Models\User;
use App\Support\Auth\UserPresenter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_presents_view_and_manage_settings_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $permissions = UserPresenter::present($admin)['permissions'];

        $this->assertIsArray($permissions);
        $this->assertContains('view settings', $permissions);
        $this->assertContains('manage settings', $permissions);
    }

    public function test_admin_me_endpoint_includes_settings_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $token = auth('api')->login($admin);

        $permissions = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->json('data.permissions');

        $this->assertIsArray($permissions);
        $this->assertContains('view settings', $permissions);
        $this->assertContains('manage settings', $permissions);
    }
}
