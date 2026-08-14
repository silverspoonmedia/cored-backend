<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\DiscordOAuthState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordOAuthCallbackWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'services.discord.client_id' => 'test-discord-client-id',
            'services.discord.client_secret' => 'test-discord-client-secret',
            'services.discord.redirect' => 'https://monolith.test/apps/auth/discord/callback',
            'services.spa.url' => 'https://spa.test',
            'app.url' => 'https://monolith.test',
        ]);
    }

    #[Test]
    public function guest_callback_with_spa_return_to_connects_and_redirects(): void
    {
        $user = User::query()->create([
            'name' => 'OAuth User',
            'email' => 'oauth-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        Http::fake([
            'discord.com/api/oauth2/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
                'scope' => 'identify connections',
            ], 200),
            'discord.com/api/users/@me' => Http::response(['id' => 'discord-user-1'], 200),
            'discord.com/api/users/@me/connections' => Http::response([], 200),
        ]);

        $state = DiscordOAuthState::encode(
            (string) $user->id,
            'profile',
            null,
            null,
            null,
            'https://spa.test/apps/profile/connections',
        );

        $this->get(route('auth.discord.callback', [
            'code' => 'oauth-code',
            'state' => $state,
        ]))
            ->assertRedirect('https://spa.test/apps/profile/connections?discord=connected');

        $this->assertDatabaseHas('identity_oauth_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::Discord->value,
            'provider_user_id' => 'discord-user-1',
        ]);
    }

    #[Test]
    public function guest_callback_denied_redirects_to_spa_with_denied_status(): void
    {
        $user = User::query()->create([
            'name' => 'OAuth Denied',
            'email' => 'oauth-denied-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        $state = DiscordOAuthState::encode(
            (string) $user->id,
            'profile',
            null,
            null,
            null,
            'https://spa.test/apps/profile/connections',
        );

        $this->get(route('auth.discord.callback', [
            'error' => 'access_denied',
            'state' => $state,
        ]))
            ->assertRedirect('https://spa.test/apps/profile/connections?discord=denied');
    }
}
