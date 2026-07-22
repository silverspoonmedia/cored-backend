<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Services\PlatformService;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PlatformCredentialApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activations = app(ProductActivationService::class);
        app(PlatformService::class)->seedDefaults();
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function admin_can_store_and_show_masked_credential(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $create = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials', [
                'label' => 'yt-primary',
                'type' => 'api_key',
                'priority' => 10,
                'secret' => ['api_key' => 'AIza-super-secret-w9Qx'],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('yt-primary', $create['label']);
        $this->assertSame('api_key', $create['type']);
        $this->assertSame('pending', $create['status']);
        $this->assertSame('…w9Qx', $create['secret_hint']);
        $this->assertTrue($create['has_secret']);
        $this->assertFalse($create['has_runtime']);
        $this->assertNull($create['verified_at']);
        $this->assertArrayNotHasKey('secret_payload', $create);
        $this->assertArrayNotHasKey('secret_fingerprint', $create);
        $this->assertArrayNotHasKey('runtime_payload', $create);
        $this->assertStringNotContainsString('AIza-super-secret-w9Qx', json_encode($create, JSON_THROW_ON_ERROR));

        $row = PlatformCredential::query()->findOrFail($create['id']);
        $raw = (string) $row->getRawOriginal('secret_payload');
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($raw));
        $this->assertStringNotContainsString('AIza-super-secret-w9Qx', $raw);

        $show = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/credentials/'.$create['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame('…w9Qx', $show['secret_hint']);
        $this->assertArrayNotHasKey('secret_payload', $show);
    }

    #[Test]
    public function root_can_list_and_update_without_secret(): void
    {
        $root = $this->userWithRole('Root');
        $this->activations->activateImplicit($root, 'Vtual');

        $created = $this->actingAsJwt($root)
            ->postJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials', [
                'label' => 'yt-a',
                'type' => 'api_key',
                'secret' => ['api_key' => 'keep-payload-1234'],
            ])
            ->assertCreated()
            ->json('data');

        $before = (string) PlatformCredential::query()->findOrFail($created['id'])->getRawOriginal('secret_payload');

        $this->actingAsJwt($root)
            ->patchJson('/api/v1/vtual/admin/credentials/'.$created['id'], [
                'label' => 'yt-a-renamed',
                'priority' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'yt-a-renamed')
            ->assertJsonPath('data.priority', 1)
            ->assertJsonPath('data.secret_hint', '…1234');

        $after = (string) PlatformCredential::query()->findOrFail($created['id'])->getRawOriginal('secret_payload');
        $this->assertSame($before, $after);

        $this->actingAsJwt($root)
            ->getJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'yt-a-renamed');
    }

    #[Test]
    public function update_with_new_secret_changes_hint(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $created = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials', [
                'label' => 'yt-rotate',
                'type' => 'api_key',
                'secret' => ['api_key' => 'old-key-aaaa'],
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/admin/credentials/'.$created['id'], [
                'secret' => ['api_key' => 'new-key-zzzz'],
            ])
            ->assertOk()
            ->assertJsonPath('data.secret_hint', '…zzzz')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.verified_at', null);
    }

    #[Test]
    public function admin_can_test_youtube_credential_and_mask_holds(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['id' => 'UC_x5XG1OV2P6uZZ5FSM9Ttw']],
            ], 200),
        ]);

        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $created = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials', [
                'label' => 'yt-probe',
                'type' => 'api_key',
                'secret' => ['api_key' => 'probe-key-w9Qx'],
            ])
            ->assertCreated()
            ->json('data');

        $body = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/credentials/'.$created['id'].'/test')
            ->assertOk()
            ->json();

        $this->assertTrue($body['data']['test']['ok']);
        $this->assertSame('youtube', $body['data']['test']['platform']);
        $this->assertSame('active', $body['data']['credential']['status']);
        $this->assertNotNull($body['data']['credential']['verified_at']);
        $this->assertArrayNotHasKey('secret_payload', $body['data']['credential']);
        $this->assertArrayNotHasKey('runtime_payload', $body['data']['credential']);
        $this->assertStringNotContainsString('probe-key-w9Qx', json_encode($body, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('access_token', json_encode($body, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function admin_can_test_twitch_and_response_hides_tokens(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'never-show-this-token',
                'expires_in' => 7200,
                'token_type' => 'bearer',
            ], 200),
            'api.twitch.tv/helix/users*' => Http::response([
                'data' => [['id' => '1', 'login' => 'twitch']],
            ], 200),
        ]);

        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');
        $twitch = Platform::query()->where('key', 'twitch')->firstOrFail();

        $created = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platforms/'.$twitch->id.'/credentials', [
                'label' => 'tw-probe',
                'type' => 'oauth_app',
                'secret' => [
                    'client_id' => 'cid-public-ish',
                    'client_secret' => 'csec-super-secret',
                ],
            ])
            ->assertCreated()
            ->json('data');

        $body = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/credentials/'.$created['id'].'/test')
            ->assertOk()
            ->json();

        $this->assertTrue($body['data']['test']['ok']);
        $this->assertSame('active', $body['data']['credential']['status']);
        $this->assertTrue($body['data']['credential']['has_runtime']);
        $this->assertNotNull($body['data']['credential']['runtime_expires_at']);
        $encoded = json_encode($body, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('never-show-this-token', $encoded);
        $this->assertStringNotContainsString('csec-super-secret', $encoded);

        $row = PlatformCredential::query()->findOrFail($created['id']);
        $rawRuntime = (string) $row->getRawOriginal('runtime_payload');
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($rawRuntime));
        $this->assertStringNotContainsString('never-show-this-token', $rawRuntime);
    }

    #[Test]
    public function user_forbidden_on_test(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($admin, 'Vtual');
        $this->activations->activateImplicit($user, 'Vtual');

        $created = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials', [
                'label' => 'yt-rbac-test',
                'type' => 'api_key',
                'secret' => ['api_key' => 'rbac-key-1111'],
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/admin/credentials/'.$created['id'].'/test')
            ->assertForbidden();
    }

    #[Test]
    public function user_role_forbidden(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials')
            ->assertForbidden();

        $this->actingAsJwt($user)
            ->postJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials', [
                'label' => 'nope',
                'type' => 'api_key',
                'secret' => ['api_key' => 'x'],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function moderator_role_forbidden(): void
    {
        $mod = $this->userWithRole('Moderator');
        $this->activations->activateImplicit($mod, 'Vtual');

        $this->actingAsJwt($mod)
            ->getJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials')
            ->assertForbidden();
    }

    #[Test]
    public function validation_rejects_wrong_type_payload(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials', [
                'label' => 'bad-oauth',
                'type' => 'oauth_app',
                'secret' => ['api_key' => 'wrong-shape'],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function disable_and_delete(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $created = $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/platforms/'.$this->youtube->id.'/credentials', [
                'label' => 'yt-del',
                'type' => 'api_key',
                'secret' => ['api_key' => 'to-delete-9999'],
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/vtual/admin/credentials/'.$created['id'].'/disable')
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/vtual/admin/credentials/'.$created['id'])
            ->assertOk();

        $this->assertDatabaseMissing('vtual_platform_credentials', ['id' => $created['id']]);
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Cred',
            'email' => strtolower($role).'-cred-api@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
