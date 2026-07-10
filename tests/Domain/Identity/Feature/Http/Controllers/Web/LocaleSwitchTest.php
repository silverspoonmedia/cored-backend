<?php

namespace Tests\Domain\Identity\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use App\Support\Localization\LocaleCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_locale_switch_sets_cookie_only(): void
    {
        $response = $this->from('/login')
            ->get('/locale/id');

        $response->assertRedirect('/login')
            ->assertCookie(LocaleCookie::name(), 'id');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_authenticated_user_locale_switch_persists_to_database(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingWithJwtCookie($user)
            ->from('/dashboard')
            ->get('/locale/id');

        $response->assertRedirect('/dashboard')
            ->assertCookie(LocaleCookie::name(), 'id');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'id',
        ]);
    }

    public function test_authenticated_user_skips_update_when_locale_unchanged(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $originalUpdatedAt = $user->fresh()->updated_at;

        $this->travel(5)->seconds();

        $this->actingWithJwtCookie($user)
            ->from('/dashboard')
            ->get('/locale/id')
            ->assertRedirect('/dashboard');

        $this->assertTrue($user->fresh()->updated_at->equalTo($originalUpdatedAt));
    }

    public function test_unsupported_locale_returns_not_found(): void
    {
        $this->get('/locale/xx')->assertNotFound();
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }
}
