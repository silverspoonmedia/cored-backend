<?php

namespace Tests\Domain\FileHosting\Qualification;

use App\Domain\FileHosting\Enums\WorkspaceRole;
use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\FileHosting\Services\FileService;
use App\Domain\FileHosting\Services\QuotaService;
use App\Domain\FileHosting\Services\WorkspaceService;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Support\Storage\S3DirectUploadEngine;
use App\Support\Storage\S3DirectUploadException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Domain\FileHosting\Qualification\Concerns\QualificationHarness;
use Tests\TestCase;

class FileHostingQualificationAnomalyTest extends TestCase
{
    use QualificationHarness;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQualification([
            'file-hosting.default_quota_bytes' => 5000,
            'file-hosting.max_concurrent_uploads' => 2,
            'file-hosting.multipart_threshold_bytes' => 10 * 1024 * 1024,
        ]);
    }

    public function test_FH_A01_double_complete_race(): void
    {
        $user = $this->userWithRole('User');
        $objectId = Str::lower(Str::random(ObjectKeyResolver::OBJECT_ID_LENGTH));
        $objectKey = app(ObjectKeyResolver::class)->objectKey($user->id, $objectId);

        $upload = FileUpload::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'disk' => 'local',
            'path' => $objectId,
            's3_upload_id' => null,
            'strategy' => FileUpload::STRATEGY_SINGLE,
            'original_name' => 'once.pdf',
            'mime' => 'application/pdf',
            'size' => 100,
            'status' => FileUpload::STATUS_PENDING,
            'parts' => [],
            'expires_at' => Carbon::now()->addHour(),
        ]);
        Storage::disk('local')->put($objectKey, str_repeat('x', 100));

        // Use real complete path (not fake) for lock semantics.
        $this->app->forgetInstance(DirectUploadService::class);
        $svc = app(DirectUploadService::class);

        $first = $svc->complete($user, $upload->id);
        $this->assertSame(FileUpload::STATUS_COMPLETED, $first['upload']->status);

        try {
            $svc->complete($user, $upload->id);
            $this->fail('Second complete must fail');
        } catch (FileHostingException $e) {
            $this->assertContains($e->statusCode(), [422, 404]);
        }

        $this->assertSame(1, HostedFile::query()->where('path', $objectId)->count());
    }

    public function test_FH_A02_concurrent_create_at_slot_limit(): void
    {
        $owner = $this->userWithRole('User');
        $this->grantPaidMain($owner);
        config(['file-hosting.base_owned_workspace_slots' => 1]);

        $svc = app(WorkspaceService::class);
        $svc->create($owner, 'Only Slot');

        try {
            $svc->create($owner, 'Over Cap');
            $this->fail('Must hit slot limit');
        } catch (FileHostingException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        $this->assertSame(1, Workspace::query()->where('owner_user_id', $owner->id)->where('status', 'active')->count());
    }

    public function test_FH_A03_quota_toctou_near_limit(): void
    {
        $user = $this->userWithRole('User');
        config(['file-hosting.default_quota_bytes' => 3000]);

        $init1 = app(DirectUploadService::class)->initiate($user, 'a.pdf', 'application/pdf', 2000);
        $init2 = null;
        try {
            $init2 = app(DirectUploadService::class)->initiate($user, 'b.pdf', 'application/pdf', 2000);
            $this->fail('Second init must reserve pending and fail');
        } catch (FileHostingException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        $this->assertNull($init2);
        $this->assertSame(2000, app(QuotaService::class)->pendingUploadBytes($user->id));
        $this->assertSame(2000, app(QuotaService::class)->usage($user->id)['pending_bytes']);
    }

    public function test_FH_A04_archive_mid_pending_upload(): void
    {
        $owner = $this->userWithRole('User', 'owner-a04@example.com');
        $this->grantPaidMain($owner);
        $ws = app(WorkspaceService::class)->create($owner, 'Mid Upload');

        $init = app(DirectUploadService::class)->initiate(
            $owner,
            'pending.pdf',
            'application/pdf',
            500,
            null,
            null,
            null,
            $ws->id,
        );
        $uploadId = $init['upload']->id;

        app(WorkspaceService::class)->archive($owner, $ws->id);

        $upload = FileUpload::query()->find($uploadId);
        $this->assertSame(FileUpload::STATUS_ABORTED, $upload->status);

        try {
            app(DirectUploadService::class)->complete($owner, $uploadId);
            $this->fail('Complete after archive must fail');
        } catch (FileHostingException $e) {
            $this->assertContains($e->statusCode(), [422, 403, 404]);
        }
    }

    public function test_FH_A05_invite_email_mismatch(): void
    {
        $owner = $this->userWithRole('User', 'owner-a05@example.com');
        $alice = $this->userWithRole('User', 'alice-a05@example.com');
        $bob = $this->userWithRole('User', 'bob-a05@example.com');
        $this->grantPaidMain($owner);

        $ws = app(WorkspaceService::class)->create($owner, 'Invite Gate');
        $invite = app(WorkspaceService::class)->invite($owner, $ws->id, $alice->email, WorkspaceRole::Editor->value);

        try {
            app(WorkspaceService::class)->acceptInvite($bob, $invite->token);
            $this->fail('Wrong email must not accept');
        } catch (FileHostingException $e) {
            $this->assertSame(403, $e->statusCode());
            $this->assertStringContainsString(
                __('api/file-hosting.workspace.invite_email_mismatch'),
                $e->getMessage(),
            );
        }
    }

    public function test_FH_A06_visibility_private_clears_signed_cache(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);
        $file = $this->seedFile($user, 'cache.pdf', 100, [
            'visibility' => 'anyone_with_link',
            'signed_download_url' => 'https://s3.example/old-signed',
            'signed_download_expires_at' => now()->addHour(),
        ]);

        app(FileService::class)->setVisibility($user, $file->id, 'private');

        $fresh = $file->fresh();
        $this->assertSame('private', $fresh->visibility);
        $this->assertNull($fresh->signed_download_url);
        $this->assertNull($fresh->signed_download_expires_at);

        // Guest Monolith path denied.
        $this->getJson('/api/v1/file-hosting/public/files/'.$file->id)
            ->assertNotFound();
    }

    public function test_FH_A07_slot_over_cap_after_entitlement_drop(): void
    {
        $owner = $this->userWithRole('User');
        $this->grantPaidMain($owner);
        config(['file-hosting.base_owned_workspace_slots' => 2]);
        $this->grantWorkspaceSlots($owner, 2); // limit 4

        $ws = app(WorkspaceService::class);
        for ($i = 1; $i <= 4; $i++) {
            $ws->create($owner, "Team {$i}");
        }
        $this->assertSame(4, Workspace::query()->where('owner_user_id', $owner->id)->where('status', 'active')->count());

        // Drop slot addon (expire entitlement).
        $slotService = Service::query()->where('code', 'file-hosting.addon.workspace-slot.monthly')->first();
        ServiceEntitlement::query()
            ->where('user_id', $owner->id)
            ->where('service_id', $slotService->id)
            ->update(['status' => EntitlementStatus::Expired, 'ends_at' => now()->subDay()]);

        // Limit collapses to base 2; existing 4 stay; create blocked.
        $usage = app(QuotaService::class)->usage($owner->id);
        $this->assertSame(2, $usage['owned_workspace_limit']);
        $this->assertSame(4, $usage['owned_workspace_used']);
        $this->assertFalse($usage['can_create_workspace']);

        try {
            $ws->create($owner, 'New After Drop');
            $this->fail('Create must be blocked over cap');
        } catch (FileHostingException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        $this->assertSame(4, Workspace::query()->where('owner_user_id', $owner->id)->where('status', 'active')->count());
    }

    public function test_FH_A08_proxy_misconfig_fail_closed(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://proxy.example.workers.dev',
            'file-hosting.upload_proxy.token' => '',
            'file-hosting.upload_proxy.disk_targets' => ['local' => 'b2-main'],
        ]);

        $engine = new S3DirectUploadEngine;
        try {
            $engine->proxyUploadParts(
                'local',
                'file-hosting/u/oid',
                S3DirectUploadEngine::STRATEGY_SINGLE,
                'application/pdf',
                null,
                [1],
            );
            $this->fail('Must fail closed');
        } catch (S3DirectUploadException $e) {
            $this->assertSame(S3DirectUploadException::PROXY_MISCONFIGURED, $e->getMessage());
        }
    }

    public function test_FH_A09_mime_spoof_client_trusted(): void
    {
        $user = $this->userWithRole('User');

        // Client claims image/png — accepted by prefix even if content is not validated.
        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'spoof.png',
                'mime' => 'image/png',
                'size' => 128,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('image/png', FileUpload::query()->find($init['upload_id'])->mime);
    }

    public function test_FH_A10_concurrent_upload_limit_server_side(): void
    {
        $user = $this->userWithRole('User');
        config(['file-hosting.max_concurrent_uploads' => 2]);

        app(DirectUploadService::class)->initiate($user, 'c1.pdf', 'application/pdf', 100);
        app(DirectUploadService::class)->initiate($user, 'c2.pdf', 'application/pdf', 100);

        try {
            app(DirectUploadService::class)->initiate($user, 'c3.pdf', 'application/pdf', 100);
            $this->fail('Third concurrent init must fail');
        } catch (FileHostingException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertStringContainsString(
                __('api/file-hosting.upload.too_many_concurrent'),
                $e->getMessage(),
            );
        }
    }
}
