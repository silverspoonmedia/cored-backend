<?php

namespace Tests\Domain\Archivd\Unit\Console;

use App\Domain\Archivd\Models\FileUpload;
use App\Domain\Identity\Models\User;
use App\Support\Storage\S3DirectUploadEngine;
use App\Support\Storage\S3DirectUploadException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneStaleUploadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'archivd.disk' => 'local',
            'archivd.stale_upload_grace_hours' => 6,
            // High enough that the expired-past-grace tests are not also tripped by
            // the inactivity arm (their rows are freshly created -> updated_at = now).
            'archivd.orphan_inactivity_minutes' => 60,
        ]);

        Storage::fake('local');
    }

    public function test_it_aborts_only_pending_sessions_past_the_grace_window(): void
    {
        $user = User::factory()->create();

        $stale = $this->makeUpload($user, FileUpload::STATUS_PENDING, Carbon::now()->subHours(10));
        $withinGrace = $this->makeUpload($user, FileUpload::STATUS_PENDING, Carbon::now()->subHours(2));
        $completed = $this->makeUpload($user, FileUpload::STATUS_COMPLETED, Carbon::now()->subHours(10));
        $alreadyAborted = $this->makeUpload($user, FileUpload::STATUS_ABORTED, Carbon::now()->subHours(10));

        $this->artisan('archivd:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_ABORTED, $stale->refresh()->status);
        $this->assertSame(FileUpload::STATUS_PENDING, $withinGrace->refresh()->status);
        $this->assertSame(FileUpload::STATUS_COMPLETED, $completed->refresh()->status);
        $this->assertSame(FileUpload::STATUS_ABORTED, $alreadyAborted->refresh()->status);
    }

    public function test_grace_zero_prunes_any_expired_pending_session(): void
    {
        config(['archivd.stale_upload_grace_hours' => 0]);

        $user = User::factory()->create();
        $justExpired = $this->makeUpload($user, FileUpload::STATUS_PENDING, Carbon::now()->subMinute());

        $this->artisan('archivd:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_ABORTED, $justExpired->refresh()->status);
    }

    public function test_it_aborts_when_s3_has_no_recent_part_activity(): void
    {
        config(['archivd.orphan_inactivity_minutes' => 30]);

        $user = User::factory()->create();

        // Not expired, DB idle 90 min; S3 newest part 2h ago (older than 30m window).
        $inactive = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->addDay(),
            Carbon::now()->subMinutes(90),
        );

        $this->fakeEngineLatestPart(Carbon::now()->subHours(2));

        $this->artisan('archivd:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_ABORTED, $inactive->refresh()->status);
    }

    public function test_it_aborts_when_s3_has_zero_parts(): void
    {
        config(['archivd.orphan_inactivity_minutes' => 30]);

        $user = User::factory()->create();

        // Init-then-abandon: no part ever landed on S3. Created 90 min ago.
        $orphan = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->addDay(),
            Carbon::now()->subMinutes(90),
        );
        FileUpload::query()->whereKey($orphan->id)->update([
            'created_at' => Carbon::now()->subMinutes(90),
            'updated_at' => Carbon::now()->subMinutes(90),
        ]);
        $orphan->refresh();

        $this->fakeEngineLatestPart(null);

        $this->artisan('archivd:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_ABORTED, $orphan->refresh()->status);
    }

    public function test_it_keeps_sessions_with_recent_s3_activity(): void
    {
        config(['archivd.orphan_inactivity_minutes' => 30]);

        $user = User::factory()->create();

        // DB looks idle, but S3 shows a part landed 5 min ago (still uploading).
        $active = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->addDay(),
            Carbon::now()->subMinutes(90),
        );

        $this->fakeEngineLatestPart(Carbon::now()->subMinutes(5));

        $this->artisan('archivd:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_PENDING, $active->refresh()->status);
    }

    public function test_it_skips_inactivity_arm_when_listparts_fails(): void
    {
        config(['archivd.orphan_inactivity_minutes' => 30]);

        $user = User::factory()->create();

        $unknown = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->addDay(),
            Carbon::now()->subMinutes(90),
        );

        // ListParts error → unknown state → never abort blindly.
        $engine = new class extends S3DirectUploadEngine
        {
            public function latestPartModifiedAt(string $diskName, string $path, string $s3UploadId): ?Carbon
            {
                throw new S3DirectUploadException(S3DirectUploadException::UPSTREAM_FAILED, 'boom');
            }
        };
        $this->app->instance(S3DirectUploadEngine::class, $engine);

        $this->artisan('archivd:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_PENDING, $unknown->refresh()->status);
    }

    public function test_it_never_prunes_single_put_sessions(): void
    {
        config(['archivd.orphan_inactivity_minutes' => 30]);

        $user = User::factory()->create();

        $singleInactive = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->addDay(),
            Carbon::now()->subMinutes(90),
            FileUpload::STRATEGY_SINGLE,
        );

        $singleExpired = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->subHours(10),
            null,
            FileUpload::STRATEGY_SINGLE,
        );

        $this->artisan('archivd:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_PENDING, $singleInactive->refresh()->status);
        $this->assertSame(FileUpload::STATUS_PENDING, $singleExpired->refresh()->status);
    }

    public function test_force_abort_pending_preserves_a_single_put_object(): void
    {
        $user = User::factory()->create();

        $single = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->addDay(),
            null,
            FileUpload::STRATEGY_SINGLE,
        );

        $objectKey = app(\App\Domain\Archivd\Support\ObjectKeyResolver::class)
            ->objectKey($single->user_id, $single->path);
        Storage::disk('local')->put($objectKey, 'full-file-bytes');

        app(\App\Domain\Archivd\Services\DirectUploadService::class)->forceAbortPending($single);

        $this->assertSame(FileUpload::STATUS_ABORTED, $single->refresh()->status);
        Storage::disk('local')->assertExists($objectKey);
    }

    private function fakeEngineLatestPart(?Carbon $latest): void
    {
        $engine = new class($latest) extends S3DirectUploadEngine
        {
            public function __construct(private readonly ?Carbon $latest)
            {
                // Intentionally NOT calling parent::__construct (engine has none).
            }

            public function latestPartModifiedAt(string $diskName, string $path, string $s3UploadId): ?Carbon
            {
                return $this->latest;
            }
        };

        $this->app->instance(S3DirectUploadEngine::class, $engine);
    }

    private function makeUpload(
        User $user,
        string $status,
        Carbon $expiresAt,
        ?Carbon $updatedAt = null,
        string $strategy = FileUpload::STRATEGY_MULTIPART,
    ): FileUpload {
        $upload = FileUpload::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'disk' => 'local',
            'path' => Str::lower(Str::random(40)),
            's3_upload_id' => $strategy === FileUpload::STRATEGY_MULTIPART ? 's3-upload-id' : null,
            'strategy' => $strategy,
            'original_name' => 'file.bin',
            'mime' => 'application/octet-stream',
            'size' => 100,
            'status' => $status,
            'parts' => [],
            'expires_at' => $expiresAt,
        ]);

        // Eloquent auto-refreshes updated_at on save, so backdate it via a raw
        // update to simulate a session with no part activity since that timestamp.
        if ($updatedAt !== null) {
            FileUpload::query()
                ->whereKey($upload->id)
                ->update(['updated_at' => $updatedAt]);
            $upload->refresh();
        }

        return $upload;
    }
}
