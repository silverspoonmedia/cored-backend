<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Models\IdentityOAuthAccount;
use App\Domain\Identity\Models\User;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithWebAuth;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ProfileConnectionsWebTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithWebAuth;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function authenticated_user_can_view_connections_page(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->configureDiscordOAuth();

        $user = User::query()->create([
            'name' => 'Connections User',
            'email' => 'connections-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('profile.connections.edit'))
            ->assertOk()
            ->assertViewIs(config('web.layout').'.profile.connections')
            ->getContent();

        $this->assertStringContainsString(__('web/identity-connections.card_title'), $html);
        $this->assertStringContainsString(__('web/identity-connections.discord_connect'), $html);
    }

    #[Test]
    public function connections_page_hides_connect_when_oauth_not_configured(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->configureDiscordOAuth(missing: true);

        $user = User::query()->create([
            'name' => 'Unconfigured User',
            'email' => 'unconfigured-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('profile.connections.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('web/identity-connections.discord_not_configured'), $html);
        $this->assertStringNotContainsString(__('web/identity-connections.discord_connect'), $html);
    }

    #[Test]
    public function connections_page_shows_linked_accounts_when_discord_connected(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);

        $user = User::query()->create([
            'name' => 'Linked User',
            'email' => 'linked-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        $account = new IdentityOAuthAccount();
        $account->user_id = $user->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = '999';
        $account->forceFill([
            'access_token' => 'test-access',
            'refresh_token' => 'test-refresh',
            'connections_snapshot' => [
                ['type' => 'youtube', 'id' => 'UC123', 'name' => 'Test', 'verified' => true],
            ],
            'connections_synced_at' => now(),
        ])->save();

        $html = $this->actingWithJwtCookie($user)
            ->get(route('profile.connections.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('web/identity-connections.discord_connected'), $html);
        $this->assertStringContainsString('Test', $html);
    }

    private function configureDiscordOAuth(bool $missing = false): void
    {
        config([
            'services.discord.client_id' => $missing ? '' : 'test-discord-client-id',
            'services.discord.client_secret' => $missing ? '' : 'test-discord-client-secret',
            'services.discord.redirect' => 'https://example.test/apps/auth/discord/callback',
        ]);
    }
}
