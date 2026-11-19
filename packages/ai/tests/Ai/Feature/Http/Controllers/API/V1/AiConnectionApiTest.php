<?php

namespace App\Domain\Ai\Tests\Feature\Http\Controllers\API\V1;

use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Services\AiConnectionResolver;
use App\Domain\Identity\Models\User;
use App\Support\Encryption\EncryptedEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AiConnectionApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        Cache::flush();
    }

    #[Test]
    public function admin_can_store_and_show_a_masked_connection(): void
    {
        $admin = $this->userWithRole('Admin');

        $create = $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections', [
                'slug' => 'microstock-vision',
                'label' => 'Microstock vision',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'test_model' => 'internal.vision',
                'timeout_seconds' => 600,
                'secret' => ['api_key' => 'sk-super-secret-w9Qx'],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('microstock-vision', $create['slug']);
        $this->assertSame('openai_compatible', $create['driver']);
        $this->assertSame('pending', $create['status']);
        $this->assertSame('…w9Qx', $create['secret_hint']);
        $this->assertTrue($create['has_secret']);
        $this->assertFalse($create['is_default']);
        $this->assertNull($create['verified_at']);

        // Secret material must never reach the payload.
        $this->assertArrayNotHasKey('secret_payload', $create);
        $this->assertArrayNotHasKey('secret_fingerprint', $create);
        $this->assertStringNotContainsString('sk-super-secret-w9Qx', json_encode($create, JSON_THROW_ON_ERROR));

        $row = AiConnection::query()->findOrFail($create['id']);
        $raw = (string) $row->getRawOriginal('secret_payload');
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($raw));
        $this->assertStringNotContainsString('sk-super-secret-w9Qx', $raw);

        $show = $this->actingAsJwt($admin)
            ->getJson('/api/v1/ai/connections/'.$create['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame('…w9Qx', $show['secret_hint']);
        $this->assertArrayNotHasKey('secret_payload', $show);
        $this->assertArrayNotHasKey('secret_fingerprint', $show);
    }

    #[Test]
    public function store_validates_the_payload(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections', [
                'slug' => 'bad slug!',
                'label' => 'X',
                'endpoint' => 'not-a-url',
                'test_model' => '',
                'secret' => ['api_key' => ''],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug', 'endpoint', 'test_model', 'secret.api_key']);
    }

    #[Test]
    public function slug_must_be_unique(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->makeConnection($admin, 'dup-slug');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections', [
                'slug' => 'dup-slug',
                'label' => 'Another',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'test_model' => 'm',
                'secret' => ['api_key' => 'k2'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/ai.connection.slug_taken'));
    }

    #[Test]
    public function test_activates_the_connection_and_stamps_verified_at(): void
    {
        Http::fake([
            'ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'pong']]]], 200),
        ]);

        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'probe-ok');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections/'.$connection->id.'/test')
            ->assertOk()
            ->assertJsonPath('data.test.ok', true)
            ->assertJsonPath('data.connection.status', 'active')
            ->assertJsonPath('data.connection.has_secret', true);

        $this->assertNotNull($connection->fresh()->verified_at);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://ai.example.test/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test-key')
                && ($request->data()['model'] ?? null) === 'probe-model';
        });
    }

    #[Test]
    public function failed_test_keeps_the_connection_pending_and_records_the_error(): void
    {
        Http::fake([
            'ai.example.test/*' => Http::response(['error' => 'nope'], 401),
        ]);

        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'probe-bad');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections/'.$connection->id.'/test')
            ->assertStatus(422)
            ->assertJsonPath('data.test.ok', false)
            ->assertJsonPath('data.connection.status', 'pending');

        $fresh = $connection->fresh();
        $this->assertNotNull($fresh->last_error_at);
        $this->assertNotNull($fresh->last_error_message);
        $this->assertNull($fresh->verified_at);
    }

    #[Test]
    public function a_failed_probe_surfaces_the_upstream_body_so_a_bad_model_alias_is_visible(): void
    {
        Http::fake([
            'ai.example.test/*' => Http::response(['detail' => 'Model not found'], 400),
        ]);

        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'probe-renamed-alias');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections/'.$connection->id.'/test')
            ->assertStatus(422)
            ->assertJsonPath('data.test.ok', false);

        $this->assertStringContainsString('HTTP 400', (string) $connection->fresh()->last_error_message);
        $this->assertStringContainsString('Model not found', (string) $connection->fresh()->last_error_message);
    }

    #[Test]
    public function editing_the_secret_or_endpoint_demotes_an_active_connection(): void
    {
        Http::fake([
            'ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'pong']]]], 200),
        ]);

        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'demote-me');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections/'.$connection->id.'/test')
            ->assertOk();

        $this->assertSame(AiConnectionStatus::Active, $connection->fresh()->status);

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/ai/connections/'.$connection->id, [
                'endpoint' => 'https://ai.example.test/v2/chat/completions',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.verified_at', null);
    }

    #[Test]
    public function label_only_edit_keeps_the_active_status(): void
    {
        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'keep-active', AiConnectionStatus::Active);

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/ai/connections/'.$connection->id, ['label' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function blank_api_key_on_update_keeps_the_stored_secret(): void
    {
        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'keep-secret', AiConnectionStatus::Active);
        $originalRaw = (string) $connection->getRawOriginal('secret_payload');

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/ai/connections/'.$connection->id, [
                'test_model' => 'new-probe-model',
                'secret' => ['api_key' => ''],
            ])
            ->assertOk()
            ->assertJsonPath('data.test_model', 'new-probe-model')
            ->assertJsonPath('data.secret_hint', '…t-key');

        $this->assertSame($originalRaw, (string) $connection->fresh()->getRawOriginal('secret_payload'));
    }

    #[Test]
    public function only_one_connection_can_be_default(): void
    {
        $admin = $this->userWithRole('Admin');
        $first = $this->makeConnection($admin, 'first', AiConnectionStatus::Active);
        $second = $this->makeConnection($admin, 'second', AiConnectionStatus::Active);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections/'.$first->id.'/default')
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections/'.$second->id.'/default')
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);

        Cache::flush();
        $this->assertSame('second', app(AiConnectionResolver::class)->defaultSlug());
    }

    #[Test]
    public function a_pending_connection_cannot_become_the_default(): void
    {
        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'pending-default');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections/'.$connection->id.'/default')
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/ai.connection.default_requires_active'));
    }

    #[Test]
    public function the_default_connection_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'default-del', AiConnectionStatus::Active);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/ai/connections/'.$connection->id.'/default')
            ->assertOk();

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/ai/connections/'.$connection->id)
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/ai.connection.delete_requires_default_handover'));
    }

    #[Test]
    public function index_lists_rows_with_row_actions_and_no_secret(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->makeConnection($admin, 'listed', AiConnectionStatus::Active);

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/ai/connections')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $response);
        $this->assertSame('listed', $response[0]['slug']);
        $this->assertNotEmpty($response[0]['actions']);
        $this->assertArrayNotHasKey('secret_payload', $response[0]);
        $this->assertStringNotContainsString('sk-test-key', json_encode($response, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function delete_removes_the_connection(): void
    {
        $admin = $this->userWithRole('Admin');
        $connection = $this->makeConnection($admin, 'gone');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/ai/connections/'.$connection->id)
            ->assertOk();

        $this->assertDatabaseMissing('ai_connections', ['id' => $connection->id]);
    }

    #[Test]
    public function moderator_can_view_but_not_manage(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->makeConnection($admin, 'moderator-view');

        $moderator = $this->userWithRole('Moderator');

        $this->actingAsJwt($moderator)
            ->getJson('/api/v1/ai/connections')
            ->assertOk();

        $this->actingAsJwt($moderator)
            ->postJson('/api/v1/ai/connections', [
                'slug' => 'nope',
                'label' => 'Nope',
                'endpoint' => 'https://ai.example.test/v1/chat/completions',
                'test_model' => 'm',
                'secret' => ['api_key' => 'k'],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function an_unprivileged_user_is_denied(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/ai/connections')
            ->assertForbidden();
    }

    private function makeConnection(
        User $actor,
        string $slug,
        AiConnectionStatus $status = AiConnectionStatus::Pending,
    ): AiConnection {
        $connection = new AiConnection([
            'slug' => $slug,
            'label' => $slug,
            'endpoint' => 'https://ai.example.test/v1/chat/completions',
            'test_model' => 'probe-model',
            'status' => $status,
            'created_by' => $actor->id,
        ]);
        $connection->secret_payload = ['api_key' => 'sk-test-key'];
        $connection->secret_hint = '…t-key';
        $connection->secret_fingerprint = str_repeat('a', 64);
        $connection->save();

        return $connection;
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
