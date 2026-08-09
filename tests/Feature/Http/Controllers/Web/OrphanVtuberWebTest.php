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

class OrphanVtuberWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private string $orphanId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->orphanId = (string) Str::uuid();
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

    public function test_index_requires_internal_role(): void
    {
        $user = $this->userWithRole('User');
        $this->fakeAuthMe();

        $this->actingWithJwtCookie($user)
            ->get(route('vtual.orphans.index'))
            ->assertForbidden();
    }

    public function test_index_renders_datatable_for_moderator(): void
    {
        $moderator = $this->userWithRole('Moderator');
        $this->fakeAuthMe();

        $html = $this->actingWithJwtCookie($moderator)
            ->get(route('vtual.orphans.index'))
            ->assertOk()
            ->assertSee(__('web/vtual-orphans.list_title'), false)
            ->getContent();

        $this->assertStringContainsString('id="vtual-orphans-table"', $html);
        $this->assertStringContainsString(route('vtual.orphans.create'), $html);
    }

    public function test_datatable_proxies_api_and_maps_actions(): void
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
            '*/api/v1/vtual/creators/orphans*' => Http::response([
                'draw' => 1,
                'recordsTotal' => 1,
                'recordsFiltered' => 1,
                'data' => [
                    [
                        'id' => $this->orphanId,
                        'display_name' => 'Orphan Fox',
                        'slug' => 'orphan-fox',
                        'channels_count' => 0,
                        'created_at' => now()->toIso8601String(),
                        'actions' => [
                            ['key' => 'edit'],
                            ['key' => 'delete'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingWithJwtCookie($admin)
            ->getJson(route('vtual.orphans.datatable', ['draw' => 1, 'start' => 0, 'length' => 50]));

        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertSame(['edit', 'delete'], collect($row['actions'])->pluck('key')->all());
        $this->assertStringEndsWith(
            parse_url(route('vtual.orphans.edit', $this->orphanId), PHP_URL_PATH),
            $row['actions'][0]['url'],
        );
    }

    public function test_create_and_edit_pages_render_ajax_forms(): void
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
            '*/api/v1/vtual/creators/orphans/'.$this->orphanId => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->orphanId,
                    'display_name' => 'Orphan Fox',
                    'slug' => 'orphan-fox',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [],
                ],
            ], 200),
            '*/api/v1/vtual/platforms' => Http::response([
                'status' => 'success',
                'data' => [
                    ['key' => 'youtube', 'name' => 'YouTube', 'enabled' => true],
                    ['key' => 'twitch', 'name' => 'Twitch', 'enabled' => true],
                ],
            ], 200),
        ]);

        $createHtml = $this->actingWithJwtCookie($admin)
            ->get(route('vtual.orphans.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-ajax-form', $createHtml);

        $editHtml = $this->actingWithJwtCookie($admin)
            ->get(route('vtual.orphans.edit', $this->orphanId))
            ->assertOk()
            ->assertViewHas('creator')
            ->assertViewHas('platforms')
            ->getContent();

        $this->assertStringContainsString('data-ajax-form', $editHtml);
        $this->assertStringContainsString('Orphan Fox', $editHtml);
        $this->assertStringContainsString(__('web/vtual-orphans.channels.section_title'), $editHtml);
        $this->assertStringContainsString('data-form-kind="orphans"', $editHtml);
        $this->assertStringContainsString('id="orphan-channels-tbody"', $editHtml);
        $this->assertStringContainsString('id="orphan-channels-empty"', $editHtml);
        $this->assertStringContainsString(__('web/vtual-orphans.channels.profile_help_youtube'), $editHtml);
        $this->assertStringContainsString('data-field-error="profile"', $editHtml);
    }

    public function test_edit_page_renders_pending_channel_rows(): void
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
            '*/api/v1/vtual/creators/orphans/'.$this->orphanId => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->orphanId,
                    'display_name' => 'Orphan Fox',
                    'slug' => 'orphan-fox',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [
                        [
                            'id' => (string) Str::uuid(),
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'profile_input' => 'https://www.youtube.com/@AizawaEma',
                            'external_id' => 'pending:abc',
                            'handle' => null,
                            'status' => 'pending',
                        ],
                    ],
                ],
            ], 200),
            '*/api/v1/vtual/platforms' => Http::response([
                'status' => 'success',
                'data' => [
                    ['key' => 'youtube', 'name' => 'YouTube', 'enabled' => true],
                ],
            ], 200),
        ]);

        $editHtml = $this->actingWithJwtCookie($admin)
            ->get(route('vtual.orphans.edit', $this->orphanId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('https://www.youtube.com/@AizawaEma', $editHtml);
        $this->assertStringContainsString(__('web/vtual-orphans.channels.status_pending'), $editHtml);
        $this->assertStringContainsString(__('web/vtual-orphans.channels.avatar'), $editHtml);
        $this->assertStringNotContainsString('id="orphan-channels-empty"', $editHtml);
        $this->assertStringContainsString('<span class="text-muted small">—</span>', $editHtml);
        $this->assertStringNotContainsString('yt3.ggpht.com', $editHtml);
    }

    public function test_edit_page_renders_active_channel_avatar(): void
    {
        $admin = $this->userWithRole('Admin');
        $channelId = (string) Str::uuid();
        $avatarUrl = 'https://yt3.ggpht.com/example-avatar=s88-c-k-c0x00ffffff-no-rj';

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
            '*/api/v1/vtual/creators/orphans/'.$this->orphanId => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->orphanId,
                    'display_name' => 'Orphan Fox',
                    'slug' => 'orphan-fox',
                    'bio' => null,
                    'avatar_url' => null,
                    'channels' => [
                        [
                            'id' => $channelId,
                            'platform' => ['key' => 'youtube', 'name' => 'YouTube'],
                            'external_id' => 'UC6YoF5-OmChAXHNff3ifR6Q',
                            'handle' => 'aizawaema_sub',
                            'display_name' => 'Aizawa Ema Sub',
                            'avatar_url' => $avatarUrl,
                            'status' => 'active',
                        ],
                    ],
                ],
            ], 200),
            '*/api/v1/vtual/platforms' => Http::response([
                'status' => 'success',
                'data' => [
                    ['key' => 'youtube', 'name' => 'YouTube', 'enabled' => true],
                ],
            ], 200),
        ]);

        $editHtml = $this->actingWithJwtCookie($admin)
            ->get(route('vtual.orphans.edit', $this->orphanId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('web/vtual-orphans.channels.avatar'), $editHtml);
        $this->assertStringContainsString($avatarUrl, $editHtml);
        $this->assertStringContainsString('rounded-circle object-fit-cover', $editHtml);
        $this->assertStringContainsString('data-channel-id="'.$channelId.'"', $editHtml);
        $this->assertStringContainsString(__('web/vtual-orphans.channels.status_active'), $editHtml);
    }
}
