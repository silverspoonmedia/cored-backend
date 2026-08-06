<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PlatformCredentialWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private string $platformId;

    private string $credentialId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->platformId = (string) Str::uuid();
        $this->credentialId = (string) Str::uuid();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }

    private function fakeAuthMe(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'test',
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'roles' => [],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
        ]);
    }

    private function fakePlatformsAndCredentials(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'test',
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'roles' => [],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
            '*/api/v1/vtual/platforms' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'id' => $this->platformId,
                        'key' => 'youtube',
                        'name' => 'YouTube',
                        'enabled' => true,
                    ],
                ],
            ], 200),
            '*/api/v1/vtual/admin/platforms/'.$this->platformId.'/credentials' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'id' => $this->credentialId,
                        'platform_id' => $this->platformId,
                        'label' => 'Primary key',
                        'type' => 'api_key',
                        'status' => 'pending',
                        'priority' => 100,
                        'secret_hint' => 'AIza…w9Qx',
                        'verified_at' => null,
                        'has_runtime' => false,
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_index_forbidden_for_moderator(): void
    {
        $moderator = $this->userWithRole('Moderator');
        $this->fakeAuthMe();

        $this->actingWithJwtCookie($moderator)
            ->get(route('vtual.platform-credentials.index'))
            ->assertForbidden();
    }

    public function test_index_renders_platform_table_for_admin(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->fakePlatformsAndCredentials();

        $html = $this->actingWithJwtCookie($admin)
            ->get(route('vtual.platform-credentials.index'))
            ->assertOk()
            ->assertSee(__('web/vtual-platform-credentials.list_title'), false)
            ->getContent();

        $this->assertStringContainsString('YouTube', $html);
        $this->assertStringContainsString(route('vtual.platform-credentials.platform', $this->platformId), $html);
        $this->assertStringContainsString(route('vtual.platform-credentials.probe'), $html);
    }

    public function test_manage_platform_renders_credentials_and_ajax_forms(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->fakePlatformsAndCredentials();

        $html = $this->actingWithJwtCookie($admin)
            ->get(route('vtual.platform-credentials.platform', $this->platformId))
            ->assertOk()
            ->assertSee('Primary key', false)
            ->getContent();

        $this->assertStringContainsString('data-ajax-form', $html);
        $this->assertStringContainsString('vtual-credential-actions', $html);
        $this->assertStringContainsString('vtualConfirmModal', $html);
        if (preg_match('/<tbody[^>]*>(.*?)<\/tbody>/is', $html, $matches)) {
            $this->assertStringNotContainsString('data-form-alert', $matches[1]);
        }
        $this->assertStringContainsString('data-credential-id="'.$this->credentialId.'"', $html);
        $this->assertStringContainsString('data-status-labels', $html);
        $this->assertStringContainsString('data-credential-field="status"', $html);
        $this->assertStringNotContainsString('location.reload', file_get_contents(resource_path('js/vtual/credentials-platform.js')));
        $this->assertStringNotContainsString('/disable', $html);
        $this->assertStringContainsString(route('vtual.platform-credentials.revoke', $this->credentialId), $html);
        $this->assertStringContainsString('data-credential-delete-form', $html);
        $this->assertStringContainsString(route('vtual.platform-credentials.create', $this->platformId), $html);
    }

    public function test_web_test_failure_forwards_credential_data(): void
    {
        $admin = $this->userWithRole('Admin');

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'test',
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'roles' => [],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
            '*/api/v1/vtual/admin/credentials/'.$this->credentialId.'/test' => Http::response([
                'status' => 'error',
                'message' => 'Probe failed',
                'data' => [
                    'credential' => [
                        'id' => $this->credentialId,
                        'platform_id' => $this->platformId,
                        'status' => 'pending',
                        'verified_at' => null,
                    ],
                    'test' => [
                        'ok' => false,
                    ],
                ],
            ], 422),
        ]);

        $this->actingWithJwtCookie($admin)
            ->postJson(route('vtual.platform-credentials.test', $this->credentialId), [
                'platform_id' => $this->platformId,
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.credential.id', $this->credentialId)
            ->assertJsonPath('data.credential.status', 'pending');
    }

    public function test_probe_page_renders_for_admin(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->fakeAuthMe();

        $this->actingWithJwtCookie($admin)
            ->get(route('vtual.platform-credentials.probe'))
            ->assertOk()
            ->assertSee(__('web/vtual.probe.title'), false)
            ->assertSee('name="platform"', false)
            ->assertSee('name="method"', false);
    }
}
