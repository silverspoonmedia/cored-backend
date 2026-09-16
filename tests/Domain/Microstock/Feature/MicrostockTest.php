<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Enums\WorkspaceRole;
use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\FileService;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Services\WorkspaceService;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Jobs\AnalyzeAssetMetadata;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Models\MarketplaceCredential;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\VisionImageResolver;
use App\Domain\Microstock\Services\CredentialService;
use App\Domain\Microstock\Services\CsvExportService;
use App\Domain\Microstock\Services\AnalysisStageService;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Services\ServiceEntitlementService;
use App\Domain\Service\Support\MicrostockCatalog;
use Database\Seeders\ArchivdRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MicrostockTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $owner;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);
        $this->seed(MicrostockRoleSeeder::class);

        config([
            'archivd.disk' => 'local',
            'archivd.default_quota_bytes' => 10 * 1024 * 1024,
            'activitylog.enabled' => false,
        ]);

        $this->owner = $this->userWithRole('User', 'owner@example.com');
        $this->other = $this->userWithRole('User', 'other@example.com');
    }

    public function test_free_user_gets_system_workspace_slot_exempt(): void
    {
        $this->grantMicrostockFree($this->owner);

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);

        $this->assertSame(WorkspaceKind::Microstock, $ws->kind);
        $this->assertSame($this->owner->id, $ws->owner_user_id);
        $this->assertTrue($ws->isSystem());

        // Idempotent
        $again = app(MicrostockService::class)->workspaceFor($this->owner);
        $this->assertSame($ws->id, $again->id);

        // Owner is a member with owner role
        $this->assertDatabaseHas('archivd_workspace_members', [
            'workspace_id' => $ws->id,
            'user_id' => $this->owner->id,
            'role' => WorkspaceRole::Owner->value,
        ]);

        // Slot-exempt: does not raise owned_workspace_used
        $usage = app(QuotaService::class)->usage($this->owner->id);
        $this->assertSame(0, $usage['owned_workspace_used']);
    }

    public function test_workspace_for_throws_403_without_entitlement(): void
    {
        try {
            app(MicrostockService::class)->workspaceFor($this->owner);
            $this->fail('workspaceFor must require Microstock activation');
        } catch (MicrostockException $e) {
            $this->assertSame(403, $e->statusCode());
            $this->assertStringContainsString(
                __('api/microstock.workspace_not_activated'),
                $e->getMessage(),
            );
        }
    }

    public function test_create_second_workspace_requires_slot_addon(): void
    {
        $this->grantMicrostockFree($this->owner);

        // Free grants 1 slot; first workspace consumes it.
        app(MicrostockService::class)->workspaceFor($this->owner);

        $service = app(MicrostockService::class);
        $this->assertSame(1, $service->slotStatus($this->owner)['limit']);
        $this->assertFalse($service->slotStatus($this->owner)['can_create']);

        try {
            $service->createWorkspace($this->owner);
            $this->fail('Second workspace must be blocked at slot cap');
        } catch (ArchivdException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        // +1 compounding slot addon raises the limit to 2.
        $this->grantMicrostockSlots($this->owner, 1);
        $this->assertSame(2, $service->slotStatus($this->owner)['limit']);
        $this->assertTrue($service->slotStatus($this->owner)['can_create']);

        $second = $service->createWorkspace($this->owner, 'Microstock 2');
        $this->assertSame(WorkspaceKind::Microstock, $second->kind);
        $this->assertSame('Microstock 2', $second->name);
        $this->assertSame(2, $service->slotStatus($this->owner)['used']);
        $this->assertFalse($service->slotStatus($this->owner)['can_create']);
    }

    public function test_create_workspace_throws_403_without_entitlement(): void
    {
        try {
            app(MicrostockService::class)->createWorkspace($this->owner);
            $this->fail('createWorkspace must require Microstock activation');
        } catch (MicrostockException $e) {
            $this->assertSame(403, $e->statusCode());
        }
    }

    public function test_system_workspace_public_share_blocked_single(): void
    {
        $this->grantMicrostockFree($this->owner);

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $file = $this->makeFile($this->owner, $ws->id);

        try {
            app(FileService::class)->setVisibility(
                $this->owner,
                $file->id,
                'anyone_with_link',
                null,
                $ws->id,
            );
            $this->fail('System workspace share must be blocked');
        } catch (ArchivdException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertStringContainsString(
                __('api/archivd.public_share_not_allowed'),
                $e->getMessage(),
            );
        }
    }

    public function test_analyze_dispatches_job_and_generates_metadata(): void
    {
        $this->bindFakeAi();
        $this->grantMicrostockFree($this->owner);

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $file = $this->makeFile($this->owner, $ws->id);

        $metadata = app(MicrostockService::class)->requestAnalysis($this->owner, $file->id);
        $this->assertSame(MetadataStatus::Pending, $metadata->status);

        // Run the queued vision + thinking jobs synchronously
        (new AnalyzeAssetMetadata((string) $metadata->id))
            ->handle(app(AnalysisStageService::class));

        $metadata->refresh();
        $this->assertSame(MetadataStatus::Generated, $metadata->status);
        $this->assertSame('Curated title', $metadata->title);
        $this->assertSame(['a', 'b'], $metadata->keywords);
        $this->assertSame('Curated description', $metadata->description);
        $this->assertSame('Illustration', $metadata->category);
    }

    public function test_finalize_persists_manual_curation(): void
    {
        $this->grantMicrostockFree($this->owner);

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $file = $this->makeFile($this->owner, $ws->id);

        AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Generated,
            'title' => 'Draft',
            'keywords' => ['x'],
        ]);

        $saved = app(MicrostockService::class)->finalize(
            $this->owner,
            $file->id,
            'Final title',
            ['k1', 'k2', ' '],
        );

        $this->assertSame(MetadataStatus::Finalized, $saved->status);
        $this->assertSame('Final title', $saved->title);
        $this->assertSame(['k1', 'k2'], $saved->keywords);
    }

    public function test_csv_export_only_finalized_rows(): void
    {
        $this->grantMicrostockFree($this->owner);

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $fileA = $this->makeFile($this->owner, $ws->id, 'a.jpg');
        $fileB = $this->makeFile($this->owner, $ws->id, 'b.jpg');

        AssetMetadata::query()->create([
            'file_id' => $fileA->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Finalized,
            'title' => 'Title A',
            'keywords' => ['one', 'two'],
            'description' => 'Description A',
            'category' => 'Illustration',
        ]);
        AssetMetadata::query()->create([
            'file_id' => $fileB->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Generated, // not finalized -> excluded
            'title' => 'Draft B',
            'keywords' => ['x'],
        ]);

        $result = app(CsvExportService::class)->build($this->owner);

        $this->assertSame(1, $result['count']);
        $this->assertSame(['filename', 'title', 'description', 'category', 'keywords'], $result['rows'][0]);
        $this->assertSame('a.jpg', $result['rows'][1][0]);
        $this->assertSame('Title A', $result['rows'][1][1]);
        $this->assertSame('Description A', $result['rows'][1][2]);
        $this->assertSame('Illustration', $result['rows'][1][3]);
        $this->assertSame('one, two', $result['rows'][1][4]);
    }

    public function test_credentials_encrypt_and_decrypt_roundtrip(): void
    {
        $credential = app(CredentialService::class)->store(
            $this->owner,
            'shutterstock',
            'main',
            ['host' => 'ftp.shutterstock.com', 'username' => 'u', 'password' => 'p'],
        );

        // Stored value is envelope JSON, not plaintext
        $raw = $credential->getRawOriginal('secret_payload');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('ftp.shutterstock.com', $raw);

        // Cast decrypts back to array
        $fresh = MarketplaceCredential::query()->find($credential->id);
        $this->assertSame('ftp.shutterstock.com', $fresh->secret_payload['host']);

        app(CredentialService::class)->delete($this->owner, $credential->id);
        $this->assertDatabaseMissing('microstock_marketplace_credentials', ['id' => $credential->id]);
    }

    public function test_invite_paid_gate_applies_to_system_workspace(): void
    {
        $this->grantMicrostockFree($this->owner);

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);

        // Free owner cannot invite
        try {
            app(WorkspaceService::class)->invite(
                $this->owner,
                $ws->id,
                $this->other->email,
                WorkspaceRole::Editor->value,
            );
            $this->fail('Free owner must not invite');
        } catch (ArchivdException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        // Paid owner can invite; free member can join
        $this->grantPaidMain($this->owner);
        app(ServiceEntitlementService::class)->forgetStorageState($this->owner->id);
        $invite = app(WorkspaceService::class)->invite(
            $this->owner,
            $ws->id,
            $this->other->email,
            WorkspaceRole::Editor->value,
        );
        $member = app(WorkspaceService::class)->acceptInvite($this->other, $invite->token);
        $this->assertSame(WorkspaceRole::Editor, $member->role);
    }

    private function bindFakeAi(): void
    {
        $this->app->instance(AiMetadataService::class, new class implements AiMetadataService
        {
            public function analyzeVision(string $imageUrl, ?string $hint = null): array
            {
                return [
                    'title' => 'Draft title',
                    'keywords' => ['a'],
                    'description' => 'Draft description',
                    'category' => 'Illustration',
                    'raw' => ['vision' => true],
                    'stage_metrics' => [['stage' => 'vision', 'multimodal' => true]],
                ];
            }

            public function generate(string $imageUrl, ?string $hint = null): array
            {
                return [
                    'title' => 'Curated title',
                    'keywords' => ['a', 'b'],
                    'description' => 'Curated description',
                    'category' => 'Illustration',
                    'raw' => ['vision' => null, 'thinking' => null],
                    'stage_metrics' => [],
                ];
            }

            public function generateBatch(array $items, ?string $hint = null): array
            {
                $out = [];
                foreach ($items as $item) {
                    $out[$item['id']] = [
                        'title' => 'Curated title',
                        'keywords' => ['a', 'b'],
                        'description' => 'Curated description',
                        'category' => 'Illustration',
                        'raw' => ['batch' => true],
                    ];
                }

                return $out;
            }

            public function curateDraft(
                string $title,
                array $keywords,
                ?string $hint = null,
                string $description = '',
                string $category = '',
                ?string $imageUrl = null,
            ): array {
                return [
                    'title' => 'Curated title',
                    'keywords' => ['a', 'b'],
                    'description' => 'Curated description',
                    'category' => 'Illustration',
                    'raw' => ['thinking' => true],
                    'stage_metrics' => [],
                ];
            }
        });

        $mock = \Mockery::mock(VisionImageResolver::class);
        $mock->shouldReceive('resolve')->andReturn('data:image/jpeg;base64,'.base64_encode('fake-preview'));
        $mock->shouldReceive('payloadByteLength')->andReturnUsing(fn (string $ref): int => strlen($ref));
        $this->app->instance(VisionImageResolver::class, $mock);
    }

    private function makeFile(User $owner, string $workspaceId, string $name = 'asset.jpg'): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspaceId,
            'folder_id' => null,
            'name' => $name,
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);
    }

    private function grantPaidMain(User $user): void
    {
        $service = Service::query()->where('code', 'archivd.plan.1tb.monthly')->first()
            ?? Service::factory()->ArchivdMain1Tb()->create();

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

    private function grantMicrostockFree(User $user): void
    {
        $service = Service::query()->where('code', MicrostockCatalog::CODE_PLAN_FREE)->first()
            ?? Service::factory()->microstockPlanFree()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => null, // one-time permanent
            ],
        );

        $this->grantMicrostockAiCredits($user);
    }

    private function grantMicrostockSlots(User $user, int $quantity): void
    {
        $service = Service::query()->where('code', MicrostockCatalog::CODE_ADDON_WORKSPACE_SLOT)->first()
            ?? Service::factory()->microstockAddonWorkspaceSlot()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => $quantity,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
            ],
        );
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
