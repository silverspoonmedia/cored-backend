<?php

namespace Tests\Domain\Identity\Unit\Services;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Models\IdentityOAuthAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\UserOAuthAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserOAuthAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserOAuthAccountService $oauthAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oauthAccounts = app(UserOAuthAccountService::class);
    }

    #[Test]
    public function connect_discord_returns_identity_oauth_account_with_connections_snapshot(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'discord.com/api/users/@me/connections' => Http::response([
                [
                    'type' => 'youtube',
                    'id' => 'UC1234567890',
                    'name' => 'Test Channel',
                    'verified' => true,
                ],
                [
                    'type' => 'twitch',
                    'id' => '999888',
                    'name' => 'TestTwitch',
                    'verified' => false,
                ],
            ], 200),
        ]);

        $account = $this->oauthAccounts->connectDiscord($user, [
            'access_token' => 'discord-access-token',
            'refresh_token' => 'discord-refresh-token',
            'expires_in' => 3600,
            'scope' => 'identify connections',
            'provider_user_id' => 'discord-user-123',
        ]);

        $this->assertInstanceOf(IdentityOAuthAccount::class, $account);
        $this->assertSame('discord-user-123', $account->provider_user_id);
        $this->assertSame(OAuthProvider::Discord, $account->provider);
        $this->assertNotNull($account->connections_synced_at);
        $this->assertCount(2, $account->connections_snapshot);
        $this->assertSame('youtube', $account->connections_snapshot[0]['type']);
        $this->assertSame('UC1234567890', $account->connections_snapshot[0]['id']);

        $this->assertDatabaseHas('identity_oauth_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::Discord->value,
            'provider_user_id' => 'discord-user-123',
        ]);
    }

    #[Test]
    public function connections_for_ownership_verify_uses_fresh_snapshot_without_discord_api_when_match(): void
    {
        $user = User::factory()->create();
        $account = new IdentityOAuthAccount();
        $account->user_id = $user->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = 'discord-user';
        $account->forceFill([
            'access_token' => 'discord-access',
            'refresh_token' => 'discord-refresh',
            'connections_snapshot' => [
                ['type' => 'youtube', 'id' => 'UC1234567890', 'name' => 'Test', 'verified' => true],
            ],
            'connections_synced_at' => now(),
        ])->save();

        Http::fake();

        $connections = $this->oauthAccounts->connectionsForOwnershipVerify(
            $user,
            fn (array $conns): bool => ($conns[0]['id'] ?? '') === 'UC1234567890',
        );

        $this->assertSame('UC1234567890', $connections[0]['id']);
        Http::assertNothingSent();
    }

    #[Test]
    public function connections_for_ownership_verify_syncs_when_snapshot_stale_and_no_match(): void
    {
        config(['services.discord.connections_snapshot_max_age_minutes' => 60]);

        $user = User::factory()->create();
        $account = new IdentityOAuthAccount();
        $account->user_id = $user->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = 'discord-user';
        $account->forceFill([
            'access_token' => 'discord-access',
            'refresh_token' => 'discord-refresh',
            'connections_snapshot' => [
                ['type' => 'youtube', 'id' => 'UC-OLD', 'name' => 'Old', 'verified' => true],
            ],
            'connections_synced_at' => now()->subHours(2),
        ])->save();

        Http::fake([
            'discord.com/api/users/@me/connections' => Http::response([
                ['type' => 'youtube', 'id' => 'UC-NEW', 'name' => 'New', 'verified' => true],
            ], 200),
        ]);

        $connections = $this->oauthAccounts->connectionsForOwnershipVerify(
            $user,
            fn (array $conns): bool => ($conns[0]['id'] ?? '') === 'UC-NEW',
        );

        $this->assertSame('UC-NEW', $connections[0]['id']);
        Http::assertSentCount(1);
    }

    #[Test]
    public function connections_for_ownership_verify_skips_sync_when_fresh_snapshot_does_not_match(): void
    {
        $user = User::factory()->create();
        $account = new IdentityOAuthAccount();
        $account->user_id = $user->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = 'discord-user';
        $account->forceFill([
            'access_token' => 'discord-access',
            'refresh_token' => 'discord-refresh',
            'connections_snapshot' => [
                ['type' => 'youtube', 'id' => 'UC-OLD', 'name' => 'Old', 'verified' => true],
            ],
            'connections_synced_at' => now(),
        ])->save();

        Http::fake();

        $connections = $this->oauthAccounts->connectionsForOwnershipVerify(
            $user,
            fn (array $conns): bool => ($conns[0]['id'] ?? '') === 'UC-MISSING',
        );

        $this->assertSame('UC-OLD', $connections[0]['id']);
        Http::assertNothingSent();
    }
}
