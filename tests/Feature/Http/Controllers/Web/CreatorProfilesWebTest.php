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

class CreatorProfilesWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    #[Test]
    public function vtuber_can_view_owned_profiles_list(): void
    {
        $this->withoutVite();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Vtual vTuber');
        app(ProductActivationService::class)->activateImplicit($user, 'Vtual');

        $creator = app(CreatorService::class)->create($user, ['display_name' => 'My Claimed VTuber']);

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
            '*/api/v1/vtual/creators/owned' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'id' => (string) $creator->id,
                        'display_name' => $creator->display_name,
                        'slug' => $creator->slug,
                        'avatar_url' => null,
                        'channels_count' => 0,
                        'profile_url' => route('vtual.vtuber.show', $creator->slug),
                    ],
                ],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.profiles.index'))
            ->assertOk()
            ->assertViewIs(config('web.layout').'.vtual.profiles.index')
            ->getContent();

        $this->assertStringContainsString(__('web/vtual-profiles.card_title'), $html);
        $this->assertStringContainsString('My Claimed VTuber', $html);
        $this->assertStringContainsString($creator->slug, $html);
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }
}
