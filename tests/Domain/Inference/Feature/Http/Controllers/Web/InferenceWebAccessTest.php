<?php

namespace Tests\Domain\Inference\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use Database\Seeders\InferenceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class InferenceWebAccessTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(InferenceCatalogSeeder::class);
    }

    public function test_management_can_open_model_catalog_index(): void
    {
        $user = $this->userWithRole('Management');
        $token = auth('api')->login($user);

        $this->withCookie(AuthCookie::NAME, $token)
            ->get(route('inference.model-cards.index'))
            ->assertOk();
    }

    public function test_user_without_catalog_permission_gets_api_forbidden_on_datatable(): void
    {
        $user = $this->userWithRole('User');
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/inference/model-cards')
            ->assertForbidden();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }
}
