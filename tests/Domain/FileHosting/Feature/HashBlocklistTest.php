<?php

namespace Tests\Domain\FileHosting\Feature;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\HashBlocklistService;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class HashBlocklistTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.default_quota_bytes' => 1024 * 1024,
        ]);
    }

    public function test_blocked_hash_cannot_be_shared_publicly(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);

        $checksum = hash('sha256', 'pirated-content');
        app(HashBlocklistService::class)->block($checksum, 'DMCA-2026-001');

        $file = $this->makeFile($user, $checksum);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/visibility', [
                'visibility' => 'anyone_with_link',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/file-hosting.public_share_blocked_hash'));

        $this->assertSame('private', $file->fresh()->visibility);
    }

    public function test_blocked_hash_denies_guest_access_as_404(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);

        $checksum = hash('sha256', 'leaked-file');
        $file = $this->makeFile($user, $checksum, 'anyone_with_link');

        // Publicly accessible before block.
        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)->assertOk();

        app(HashBlocklistService::class)->block($checksum, 'takedown');

        // After block: show + download both deny as 404.
        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)->assertStatus(404);
        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id.'/download')->assertStatus(404);
    }

    public function test_bulk_share_rejected_when_any_file_is_blocked(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);

        $blockedChecksum = hash('sha256', 'bad');
        app(HashBlocklistService::class)->block($blockedChecksum);

        $clean = $this->makeFile($user, hash('sha256', 'fine'));
        $blocked = $this->makeFile($user, $blockedChecksum);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/files/bulk-visibility', [
                'ids' => [$clean->id, $blocked->id],
                'visibility' => 'anyone_with_link',
            ])
            ->assertStatus(422);

        $this->assertSame('private', $clean->fresh()->visibility);
        $this->assertSame('private', $blocked->fresh()->visibility);
    }

    public function test_unblock_restores_share_and_guest_access(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);

        $checksum = hash('sha256', 'restored');
        $service = app(HashBlocklistService::class);
        $service->block($checksum);
        $service->unblock($checksum);

        $file = $this->makeFile($user, $checksum);

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/visibility', [
                'visibility' => 'anyone_with_link',
            ])
            ->assertOk();

        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)->assertOk();
    }

    public function test_resource_exposes_is_hash_blocked(): void
    {
        $user = $this->userWithRole('User');

        $blocked = $this->makeFile($user, hash('sha256', 'flagged'));
        app(HashBlocklistService::class)->block($blocked->checksum);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/files/'.$blocked->id)
            ->assertOk()
            ->assertJsonPath('data.is_hash_blocked', true);
    }

    public function test_artisan_blocklist_add_and_remove(): void
    {
        $checksum = hash('sha256', 'cli');

        $this->artisan('file-hosting:blocklist-hash', ['sha256' => $checksum, '--reason' => 'r'])
            ->assertSuccessful();

        $this->assertTrue(app(HashBlocklistService::class)->isBlocked($checksum));

        $this->artisan('file-hosting:blocklist-hash', ['sha256' => $checksum, '--remove' => true])
            ->assertSuccessful();

        $this->assertFalse(app(HashBlocklistService::class)->isBlocked($checksum));
    }

    private function makeFile(User $user, string $checksum, string $visibility = 'private'): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => Str::random(8).'.bin',
            'disk' => 'local',
            'path' => Str::lower(Str::random(40)),
            'size' => 10,
            'mime' => 'application/octet-stream',
            'checksum' => $checksum,
            'visibility' => $visibility,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'email' => 'u'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function grantPaidMain(User $user): void
    {
        $service = Service::query()->where('code', 'file-hosting.plan.1tb.monthly')->first()
            ?? Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
            ],
        );
    }
}
