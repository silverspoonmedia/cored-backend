<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Auth\AuthCookie;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreatorProfileManageWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    #[Test]
    public function owner_can_view_manage_page_and_see_channel_claim_form(): void
    {
        $this->withoutVite();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Vtual vTuber');
        app(ProductActivationService::class)->activateImplicit($user, 'Vtual');

        $creator = app(CreatorService::class)->create($user, ['display_name' => 'Manage Me']);

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => [],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
            '*/api/v1/vtual/vtubers/'.$creator->slug => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => (string) $creator->id,
                    'slug' => $creator->slug,
                    'display_name' => $creator->display_name,
                ],
            ], 200),
            '*/api/v1/vtual/creators/'.$creator->id => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => (string) $creator->id,
                    'slug' => $creator->slug,
                    'display_name' => $creator->display_name,
                    'bio' => null,
                    'avatar_url' => null,
                    'status' => 'active',
                    'my_role' => 'owner',
                ],
            ], 200),
            '*/api/v1/vtual/creators/'.$creator->id.'/channels' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/creators/'.$creator->id.'/channels/archived' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/creators/'.$creator->id.'/channel-claims' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/creators/'.$creator->id.'/links' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/platforms' => Http::response([
                'status' => 'success',
                'data' => [
                    ['key' => 'youtube', 'name' => 'YouTube', 'enabled' => true],
                ],
            ], 200),
            '*/api/v1/vtual/creators' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'id' => (string) $creator->id,
                        'slug' => $creator->slug,
                        'display_name' => $creator->display_name,
                        'status' => 'active',
                        'my_role' => 'owner',
                    ],
                ],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.profiles.show', $creator->slug))
            ->assertOk()
            ->assertViewIs(config('web.layout').'.vtual.profiles.show')
            ->getContent();

        $this->assertStringContainsString(__('web/vtual.profiles.section_channels'), $html);
        $this->assertStringContainsString(__('web/vtual.add_channel'), $html);
        $this->assertStringContainsString('Manage Me', $html);
    }

    #[Test]
    public function verify_discord_route_is_registered_for_profiles_manage(): void
    {
        $this->assertNotNull(route('vtual.profiles.channel-claims.verify-discord', ['creator' => 'slug', 'claim' => '019fdb00-8558-70b7-849f-b3ecb422fa52'], false));
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }
}
