<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AdminSettingsControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_empty_put_returns_empty_payload_validation_error(): void
    {
        $this->actingAsJwt($this->userWithRole('Admin'))
            ->putJson('/api/v1/admin/settings/platform', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings'])
            ->assertJsonPath('errors.settings.0', __('settings.empty_payload'));
    }

    public function test_underscore_put_updates_setting(): void
    {
        $response = $this->actingAsJwt($this->userWithRole('Admin'))
            ->putJson('/api/v1/admin/settings/platform', [
                'platform_jwt_ttl' => 181,
            ])
            ->assertOk()
            ->assertJsonPath('message', __('settings.updated'))
            ->assertJsonPath('data.group', 'platform');

        $settings = $response->json('data.settings');
        $this->assertIsArray($settings);
        $this->assertSame(181, $settings['platform.jwt.ttl']['value']);
        $this->assertSame('database', $settings['platform.jwt.ttl']['source']);
    }

    public function test_dotted_put_aliases_to_underscore_and_updates(): void
    {
        $response = $this->actingAsJwt($this->userWithRole('Admin'))
            ->putJson('/api/v1/admin/settings/platform', [
                'platform.jwt.ttl' => 182,
            ])
            ->assertOk()
            ->assertJsonPath('message', __('settings.updated'));

        $settings = $response->json('data.settings');
        $this->assertIsArray($settings);
        $this->assertSame(182, $settings['platform.jwt.ttl']['value']);
        $this->assertSame('database', $settings['platform.jwt.ttl']['source']);
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
