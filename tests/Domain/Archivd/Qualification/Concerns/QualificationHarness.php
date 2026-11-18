<?php

namespace Tests\Domain\Archivd\Qualification\Concerns;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\DirectUploadService;
use App\Domain\Archivd\Services\SignedDownloadService;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeArchivdDirectUploadService;
use Tests\Fakes\FakeSignedDownloadService;

/**
 * Shared setup for Archivd qualification matrix (AR-L/N/M/H/A).
 */
trait QualificationHarness
{
    protected function setUpQualification(array $extraConfig = []): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);

        config(array_merge([
            'archivd.disk' => 'local',
            'archivd.directory' => 'archivd',
            'archivd.default_quota_bytes' => 10 * 1024 * 1024,
            'archivd.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
            'archivd.signed_download_ttl_minutes' => 15,
            'archivd.signed_download_cache_margin_seconds' => 60,
            'archivd.multipart_threshold_bytes' => 10 * 1024 * 1024,
            'archivd.max_concurrent_uploads' => 10,
            'archivd.upload_proxy.base_url' => '',
            'archivd.upload_proxy.token' => '',
            'archivd.upload_proxy.disk_targets' => [],
            'activitylog.enabled' => true,
        ], $extraConfig));

        Storage::fake('local');

        $this->app->instance(
            DirectUploadService::class,
            $this->app->make(FakeArchivdDirectUploadService::class),
        );
        $this->app->instance(
            SignedDownloadService::class,
            $this->app->make(FakeSignedDownloadService::class),
        );
    }

    protected function userWithRole(string $role, ?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? ('u'.uniqid('', true).'@example.com'),
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    protected function grantPaidMain(User $user): void
    {
        $service = Service::query()->where('code', 'archivd.plan.1tb.monthly')->first()
            ?? Service::factory()->archivdMain1Tb()->create();

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

    protected function grantGraceMain(User $user): void
    {
        $service = Service::query()->where('code', 'archivd.plan.1tb.monthly')->first()
            ?? Service::factory()->archivdMain1Tb()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Grace,
                'quantity' => 1,
                'starts_at' => now()->subDays(40),
                'ends_at' => now()->subDay(),
            ],
        );
    }

    protected function grantWorkspaceSlots(User $user, int $quantity = 1): void
    {
        $service = Service::query()->where('code', 'archivd.addon.workspace-slot.monthly')->first()
            ?? Service::factory()->archivdAddonWorkspaceSlot()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => max(1, $quantity),
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
            ],
        );
    }

    /**
     * @return array{upload_id: string, path: string, file?: array}
     */
    protected function uploadComplete(User $user, string $name, int $size = 1024, ?string $workspaceId = null, ?string $folderId = null): array
    {
        $payload = [
            'name' => $name,
            'mime' => 'application/pdf',
            'size' => $size,
        ];
        if ($folderId !== null) {
            $payload['folder_id'] = $folderId;
        }
        if ($workspaceId !== null) {
            $payload['workspace_id'] = $workspaceId;
        }

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/uploads', $payload)
            ->assertCreated()
            ->json('data');

        $complete = $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/uploads/'.$init['upload_id'].'/complete', [
                'parts' => [['part_number' => 1, 'etag' => 'abc', 'size' => $size]],
            ])
            ->assertOk()
            ->json('data');

        return array_merge($init, ['file' => $complete['file']]);
    }

    protected function seedFile(User $user, string $name, int $size = 100, array $extra = []): HostedFile
    {
        return HostedFile::query()->create(array_merge([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => $name,
            'disk' => 'local',
            'path' => substr(hash('sha256', $user->id.'|'.$name.'|'.uniqid('', true)), 0, 40),
            'size' => $size,
            'mime' => 'application/pdf',
            'visibility' => 'private',
        ], $extra));
    }
}
