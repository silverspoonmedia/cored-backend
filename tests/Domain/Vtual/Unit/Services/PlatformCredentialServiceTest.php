<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Events\PlatformCredentialChanged;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\PlatformService;
use App\Support\Encryption\EncryptedEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PlatformCredentialServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private PlatformCredentialService $service;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        app(PlatformService::class)->seedDefaults();
        $this->service = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function create_starts_pending_and_is_not_picked_until_active(): void
    {
        $admin = $this->userWithRole('Admin');

        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-primary',
            'type' => PlatformCredentialType::ApiKey->value,
            'priority' => 10,
            'status' => PlatformCredentialStatus::Active->value, // ignored
            'secret' => ['api_key' => 'primary-key-w9Qx'],
        ]);

        $raw = (string) $credential->getRawOriginal('secret_payload');
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($raw));
        $this->assertStringNotContainsString('primary-key-w9Qx', $raw);
        $this->assertSame('…w9Qx', $credential->secret_hint);
        $this->assertSame(PlatformCredentialStatus::Pending, $credential->status);
        $this->assertNull($credential->verified_at);
        $this->assertNull($this->service->pickActive($this->youtube));
    }

    #[Test]
    public function pick_active_orders_by_priority_among_active_only(): void
    {
        $admin = $this->userWithRole('Admin');

        $low = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-secondary',
            'type' => PlatformCredentialType::ApiKey->value,
            'priority' => 50,
            'secret' => ['api_key' => 'secondary-key-zzzz'],
        ]);
        $high = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-primary',
            'type' => PlatformCredentialType::ApiKey->value,
            'priority' => 10,
            'secret' => ['api_key' => 'primary-key-w9Qx'],
        ]);

        // Force active without HTTP (unit path for pickActive ordering).
        $low->forceFill(['status' => PlatformCredentialStatus::Active])->save();
        $high->forceFill(['status' => PlatformCredentialStatus::Active])->save();

        $picked = $this->service->pickActive($this->youtube);
        $this->assertNotNull($picked);
        $this->assertSame($high->id, $picked->id);

        $this->service->revoke($high);
        $pickedAfter = $this->service->pickActive($this->youtube);
        $this->assertNotNull($pickedAfter);
        $this->assertSame($low->id, $pickedAfter->id);
        $this->assertSame(PlatformCredentialStatus::Revoked, $high->fresh()->status);
    }

    #[Test]
    public function update_on_active_credential_demotes_to_pending(): void
    {
        $admin = $this->userWithRole('Admin');
        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-active-edit',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'active-edit-key-1234'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();

        $updated = $this->service->update($credential, [
            'label' => 'yt-active-edit-renamed',
        ]);

        $this->assertSame('yt-active-edit-renamed', $updated->label);
        $this->assertSame(PlatformCredentialStatus::Pending, $updated->status);
        $this->assertNull($updated->verified_at);
    }

    #[Test]
    public function delete_active_requires_revoke_first(): void
    {
        $admin = $this->userWithRole('Admin');
        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-no-hard-delete',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'no-delete-active-99'],
        ]);
        $credential->forceFill(['status' => PlatformCredentialStatus::Active])->save();

        $this->expectException(VtualException::class);
        $this->service->delete($credential);
    }

    #[Test]
    public function update_without_secret_keeps_payload(): void
    {
        $admin = $this->userWithRole('Admin');
        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-keep',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'keep-me-secret-1234'],
        ]);

        $before = (string) $credential->getRawOriginal('secret_payload');
        $beforeFp = $credential->secret_fingerprint;

        $updated = $this->service->update($credential, [
            'label' => 'yt-keep-renamed',
            'priority' => 5,
        ]);

        $this->assertSame('yt-keep-renamed', $updated->label);
        $this->assertSame(5, $updated->priority);
        $this->assertSame($before, (string) $updated->getRawOriginal('secret_payload'));
        $this->assertSame($beforeFp, $updated->secret_fingerprint);
    }

    #[Test]
    public function update_with_secret_refreshes_hint_and_resets_to_pending(): void
    {
        $admin = $this->userWithRole('Admin');
        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-rotate',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'old-secret-aaaa'],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
            'runtime_payload' => [
                'access_token' => 'should-clear',
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ])->save();

        $beforeFp = $credential->secret_fingerprint;
        $updated = $this->service->update($credential, [
            'secret' => ['api_key' => 'new-secret-bbbb'],
        ]);

        $this->assertNotSame($beforeFp, $updated->secret_fingerprint);
        $this->assertSame('…bbbb', $updated->secret_hint);
        $this->assertSame(['api_key' => 'new-secret-bbbb'], $updated->decryptedPayload());
        $this->assertSame(PlatformCredentialStatus::Pending, $updated->status);
        $this->assertNull($updated->verified_at);
        $this->assertFalse($updated->hasRuntimePayload());
    }

    #[Test]
    public function resolve_payload_uses_decrypt_cache_and_survives_past_global_ttl(): void
    {
        config([
            'encryption.decrypted_cache_max_ttl' => 86400,
            'encryption.decrypted_cache_ttl_by_resource_type' => [
                'vtual_platform_credentials' => 0,
            ],
            'encryption.audit_decrypts' => 'cache_miss',
        ]);

        Carbon::setTestNow('2026-01-01 00:00:00');
        Cache::flush();
        Activity::query()->delete();

        $admin = $this->userWithRole('Admin');
        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-cache',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'cached-key-9999'],
        ]);

        // Fresh model so cast reads from DB (not in-memory attribute).
        $loaded = $credential->fresh();
        $this->assertSame(['api_key' => 'cached-key-9999'], $this->service->resolvePayload($loaded));

        Carbon::setTestNow('2026-01-03 00:00:00');
        $loadedAgain = $credential->fresh();
        $this->assertSame(['api_key' => 'cached-key-9999'], $this->service->resolvePayload($loadedAgain));

        // Only first decrypt should audit under cache_miss mode.
        $this->assertSame(1, $this->cryptoDecryptCountFor($credential->id));

        Carbon::setTestNow();
    }

    #[Test]
    public function mark_used_does_not_invalidate_cache_mark_failed_does(): void
    {
        config([
            'encryption.audit_decrypts' => 'cache_miss',
            'encryption.decrypted_cache_ttl_by_resource_type' => [
                'vtual_platform_credentials' => 0,
            ],
        ]);

        Cache::flush();
        Activity::query()->delete();

        $admin = $this->userWithRole('Admin');
        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-fail',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'fail-key-aaaa'],
        ]);

        $this->service->resolvePayload($credential->fresh());
        $this->assertSame(1, $this->cryptoDecryptCountFor($credential->id));

        $this->service->markUsed($credential->fresh());
        $this->assertNotNull($credential->fresh()->last_used_at);

        $this->service->resolvePayload($credential->fresh());
        $this->assertSame(
            1,
            $this->cryptoDecryptCountFor($credential->id),
            'markUsed must not force re-decrypt',
        );

        $this->service->markFailed($credential->fresh(), 'upstream 401');
        $failed = $credential->fresh();
        $this->assertNotNull($failed->last_error_at);
        $this->assertSame('upstream 401', $failed->last_error_message);

        $this->service->resolvePayload($failed->fresh());
        $this->assertSame(
            2,
            $this->cryptoDecryptCountFor($credential->id),
            'markFailed must invalidate decrypt cache',
        );
    }

    #[Test]
    public function test_youtube_success_activates_without_runtime(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['id' => 'UC_x5XG1OV2P6uZZ5FSM9Ttw']],
            ], 200),
        ]);

        $admin = $this->userWithRole('Admin');
        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-test',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'yt-key-test-aaaa'],
        ]);

        Event::fake([PlatformCredentialChanged::class]);
        $this->enableBroadcast();

        $result = $this->service->test($credential);
        $this->assertTrue($result->ok);
        $this->assertSame('youtube', $result->platformKey);

        $fresh = $credential->fresh();
        $this->assertSame(PlatformCredentialStatus::Active, $fresh->status);
        $this->assertNotNull($fresh->verified_at);
        $this->assertFalse($fresh->hasRuntimePayload());
        $this->assertNull($fresh->last_error_message);
        $this->assertSame($fresh->id, $this->service->pickActive($this->youtube)?->id);

        Event::assertDispatched(PlatformCredentialChanged::class, function (PlatformCredentialChanged $e) use ($credential): bool {
            return $e->action === 'tested'
                && $e->credentialId === (string) $credential->id
                && $e->status === PlatformCredentialStatus::Active->value;
        });
    }

    #[Test]
    public function test_youtube_failure_stays_pending_with_error(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'error' => ['message' => 'API key not valid'],
            ], 400),
        ]);

        $admin = $this->userWithRole('Admin');
        $credential = $this->service->create($admin, $this->youtube, [
            'label' => 'yt-bad',
            'type' => PlatformCredentialType::ApiKey->value,
            'secret' => ['api_key' => 'bad-key-zzzz'],
        ]);

        Event::fake([PlatformCredentialChanged::class]);
        $this->enableBroadcast();

        $result = $this->service->test($credential);
        $this->assertFalse($result->ok);

        $fresh = $credential->fresh();
        $this->assertSame(PlatformCredentialStatus::Pending, $fresh->status);
        $this->assertNull($fresh->verified_at);
        $this->assertNotNull($fresh->last_error_at);
        $this->assertStringContainsString('API key not valid', (string) $fresh->last_error_message);

        Event::assertDispatched(PlatformCredentialChanged::class, function (PlatformCredentialChanged $e) use ($credential): bool {
            return $e->action === 'test_failed'
                && $e->credentialId === (string) $credential->id
                && $e->status === PlatformCredentialStatus::Pending->value;
        });
    }

    #[Test]
    public function test_twitch_success_stores_encrypted_runtime_token(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'twitch-app-token-secret',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ], 200),
            'api.twitch.tv/helix/users*' => Http::response([
                'data' => [['id' => '1', 'login' => 'twitch']],
            ], 200),
        ]);

        $admin = $this->userWithRole('Admin');
        $twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        $credential = $this->service->create($admin, $twitch, [
            'label' => 'tw-app',
            'type' => PlatformCredentialType::OauthApp->value,
            'secret' => [
                'client_id' => 'tw-client-id',
                'client_secret' => 'tw-client-secret',
            ],
        ]);

        $result = $this->service->test($credential);
        $this->assertTrue($result->ok);

        $fresh = $credential->fresh();
        $this->assertSame(PlatformCredentialStatus::Active, $fresh->status);
        $this->assertTrue($fresh->hasRuntimePayload());
        $this->assertNotNull($fresh->runtimeExpiresAt());

        $rawRuntime = (string) $fresh->getRawOriginal('runtime_payload');
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($rawRuntime));
        $this->assertStringNotContainsString('twitch-app-token-secret', $rawRuntime);

        $runtime = $fresh->runtime_payload;
        $this->assertIsArray($runtime);
        $this->assertSame('twitch-app-token-secret', $runtime['access_token'] ?? null);
    }

    #[Test]
    public function get_app_access_token_reuses_runtime_until_skew_then_remints(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');

        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::sequence()
                ->push([
                    'access_token' => 'token-first',
                    'expires_in' => 3600,
                    'token_type' => 'bearer',
                ], 200)
                ->push([
                    'access_token' => 'token-second',
                    'expires_in' => 3600,
                    'token_type' => 'bearer',
                ], 200),
            'api.twitch.tv/helix/users*' => Http::response([
                'data' => [['id' => '1', 'login' => 'twitch']],
            ], 200),
        ]);

        $admin = $this->userWithRole('Admin');
        $twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
        $credential = $this->service->create($admin, $twitch, [
            'label' => 'tw-reuse',
            'type' => PlatformCredentialType::OauthApp->value,
            'secret' => [
                'client_id' => 'tw-client-id',
                'client_secret' => 'tw-client-secret',
            ],
        ]);

        $this->assertTrue($this->service->test($credential)->ok);
        $credential = $credential->fresh(['platform']);

        $first = $this->service->getAppAccessToken($credential);
        $this->assertSame('token-first', $first);

        // Still within skew window — no re-mint.
        Carbon::setTestNow('2026-07-22 12:30:00');
        $second = $this->service->getAppAccessToken($credential->fresh(['platform']));
        $this->assertSame('token-first', $second);

        // Near expiry (skew default 300s; expires_at was +3600 from mint).
        Carbon::setTestNow('2026-07-22 12:56:00');
        $third = $this->service->getAppAccessToken($credential->fresh(['platform']));
        $this->assertSame('token-second', $third);

        Http::assertSentCount(4); // 2 mint + 2 helix during test+remint (reuse path has no HTTP)
        Carbon::setTestNow();
    }

    private function cryptoDecryptCountFor(string $resourceId): int
    {
        return Activity::query()
            ->where('log_name', 'crypto')
            ->where('event', 'field_decrypted')
            ->get()
            ->filter(fn (Activity $a): bool => ($a->properties['resource_id'] ?? null) === $resourceId)
            ->count();
    }

    private function enableBroadcast(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' User',
            'email' => strtolower($role).'-cred-svc@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
