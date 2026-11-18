<?php

namespace Tests\Domain\Ai\Unit\Services;

use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Services\AiConnectionService;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AiConnectionServiceTest extends TestCase
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
    public function create_always_starts_pending_with_a_hashed_fingerprint(): void
    {
        $actor = $this->userWithRole('Admin');

        $connection = app(AiConnectionService::class)->create($actor, [
            'slug' => 'My Connection!',
            'label' => ' My connection ',
            'endpoint' => 'https://ai.example.test/v1/chat/completions/',
            'test_model' => 'probe',
            'secret' => ['api_key' => 'sk-secret-abcd'],
        ]);

        $this->assertSame('my-connection', $connection->slug);
        $this->assertSame('My connection', $connection->label);
        $this->assertSame('https://ai.example.test/v1/chat/completions', $connection->endpoint);
        $this->assertSame(AiConnectionStatus::Pending, $connection->status);
        $this->assertFalse($connection->is_default);
        $this->assertSame('…abcd', $connection->secret_hint);
        $this->assertSame(64, strlen((string) $connection->secret_fingerprint));
        $this->assertSame($actor->id, $connection->created_by);
    }

    #[Test]
    public function create_can_promote_the_first_connection_to_default(): void
    {
        $actor = $this->userWithRole('Admin');

        $connection = app(AiConnectionService::class)->create($actor, [
            'slug' => 'first-default',
            'label' => 'First',
            'endpoint' => 'https://ai.example.test/v1/chat/completions',
            'test_model' => 'probe',
            'secret' => ['api_key' => 'sk-1'],
            'is_default' => true,
        ]);

        $this->assertTrue($connection->is_default);
    }

    #[Test]
    public function update_with_identical_secret_keeps_the_active_status(): void
    {
        $actor = $this->userWithRole('Admin');
        $service = app(AiConnectionService::class);

        $connection = $service->create($actor, [
            'slug' => 'same-secret',
            'label' => 'Same',
            'endpoint' => 'https://ai.example.test/v1/chat/completions',
            'test_model' => 'probe',
            'secret' => ['api_key' => 'sk-same'],
        ]);
        $connection->forceFill(['status' => AiConnectionStatus::Active, 'verified_at' => now()])->save();
        $raw = (string) $connection->getRawOriginal('secret_payload');

        $updated = $service->update($actor, $connection, ['secret' => ['api_key' => 'sk-same']]);

        $this->assertSame(AiConnectionStatus::Active, $updated->status);
        $this->assertSame($raw, (string) $updated->getRawOriginal('secret_payload'));
    }

    #[Test]
    public function update_with_a_new_secret_demotes_the_connection(): void
    {
        $actor = $this->userWithRole('Admin');
        $service = app(AiConnectionService::class);

        $connection = $service->create($actor, [
            'slug' => 'new-secret',
            'label' => 'New',
            'endpoint' => 'https://ai.example.test/v1/chat/completions',
            'test_model' => 'probe',
            'secret' => ['api_key' => 'sk-old'],
        ]);
        $connection->forceFill(['status' => AiConnectionStatus::Active, 'verified_at' => now()])->save();

        $updated = $service->update($actor, $connection, ['secret' => ['api_key' => 'sk-new']]);

        $this->assertSame(AiConnectionStatus::Pending, $updated->status);
        $this->assertNull($updated->verified_at);
        $this->assertSame('…-new', $updated->secret_hint);
    }

    #[Test]
    public function set_default_demotes_every_other_row(): void
    {
        $actor = $this->userWithRole('Admin');
        $service = app(AiConnectionService::class);

        $first = $this->active($service, $actor, 'a-first');
        $second = $this->active($service, $actor, 'b-second');

        $service->setDefault($first);
        $service->setDefault($second);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    #[Test]
    public function slug_collision_is_rejected(): void
    {
        $actor = $this->userWithRole('Admin');
        $service = app(AiConnectionService::class);

        $this->active($service, $actor, 'taken');

        $this->expectException(\App\Domain\Ai\Exceptions\AiException::class);

        $service->create($actor, [
            'slug' => 'taken',
            'label' => 'Dup',
            'endpoint' => 'https://ai.example.test/v1/chat/completions',
            'test_model' => 'probe',
            'secret' => ['api_key' => 'sk-dup'],
        ]);
    }

    #[Test]
    public function an_invalid_endpoint_is_rejected(): void
    {
        $actor = $this->userWithRole('Admin');

        $this->expectException(\App\Domain\Ai\Exceptions\AiException::class);

        app(AiConnectionService::class)->create($actor, [
            'slug' => 'bad-endpoint',
            'label' => 'Bad',
            'endpoint' => 'ai.example.test',
            'test_model' => 'probe',
            'secret' => ['api_key' => 'sk-bad'],
        ]);
    }

    #[Test]
    public function deleting_the_default_row_is_refused(): void
    {
        $actor = $this->userWithRole('Admin');
        $service = app(AiConnectionService::class);

        $connection = $this->active($service, $actor, 'default-del');
        $service->setDefault($connection);

        $this->expectException(\App\Domain\Ai\Exceptions\AiException::class);

        $service->delete($connection->fresh());
    }

    #[Test]
    public function mark_failed_truncates_the_message_to_the_column_width(): void
    {
        $actor = $this->userWithRole('Admin');
        $service = app(AiConnectionService::class);
        $connection = $this->active($service, $actor, 'long-error');

        $service->markFailed($connection, str_repeat('x', 500));

        $fresh = $connection->fresh();
        $this->assertNotNull($fresh->last_error_at);
        $this->assertSame(255, mb_strlen((string) $fresh->last_error_message));
    }

    #[Test]
    public function secret_payload_is_encrypted_at_rest(): void
    {
        $actor = $this->userWithRole('Admin');

        $connection = app(AiConnectionService::class)->create($actor, [
            'slug' => 'encrypted',
            'label' => 'Encrypted',
            'endpoint' => 'https://ai.example.test/v1/chat/completions',
            'test_model' => 'probe',
            'secret' => ['api_key' => 'sk-plaintext-must-not-appear'],
        ]);

        $raw = (string) $connection->fresh()->getRawOriginal('secret_payload');

        $this->assertStringNotContainsString('sk-plaintext-must-not-appear', $raw);
        $this->assertSame(
            ['api_key' => 'sk-plaintext-must-not-appear'],
            $connection->fresh()->secret_payload,
        );
    }

    private function active(AiConnectionService $service, User $actor, string $slug): AiConnection
    {
        $connection = $service->create($actor, [
            'slug' => $slug,
            'label' => $slug,
            'endpoint' => 'https://ai.example.test/v1/chat/completions',
            'test_model' => 'probe',
            'secret' => ['api_key' => 'sk-'.$slug],
        ]);

        $connection->forceFill(['status' => AiConnectionStatus::Active, 'verified_at' => now()])->save();

        return $connection->fresh();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }
}
