<?php

namespace Tests\Domain\FileHosting\Unit\Console;

use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\Identity\Models\User;
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
            'file-hosting.disk' => 'local',
            'file-hosting.stale_upload_grace_hours' => 6,
            // High enough that the expired-past-grace tests are not also tripped by
            // the inactivity arm (their rows are freshly created -> updated_at = now).
            'file-hosting.orphan_inactivity_minutes' => 60,
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

        $this->artisan('file-hosting:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_ABORTED, $stale->refresh()->status);
        $this->assertSame(FileUpload::STATUS_PENDING, $withinGrace->refresh()->status);
        $this->assertSame(FileUpload::STATUS_COMPLETED, $completed->refresh()->status);
        $this->assertSame(FileUpload::STATUS_ABORTED, $alreadyAborted->refresh()->status);
    }

    public function test_grace_zero_prunes_any_expired_pending_session(): void
    {
        config(['file-hosting.stale_upload_grace_hours' => 0]);

        $user = User::factory()->create();
        $justExpired = $this->makeUpload($user, FileUpload::STATUS_PENDING, Carbon::now()->subMinute());

        $this->artisan('file-hosting:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_ABORTED, $justExpired->refresh()->status);
    }

    public function test_it_aborts_pending_sessions_inactive_past_the_orphan_window(): void
    {
        config(['file-hosting.orphan_inactivity_minutes' => 30]);

        $user = User::factory()->create();

        // Not expired (TTL far in the future) but no part activity for 90 min.
        $inactive = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->addDay(),
            Carbon::now()->subMinutes(90),
        );

        $this->artisan('file-hosting:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_ABORTED, $inactive->refresh()->status);
    }

    public function test_it_leaves_active_sessions_within_the_orphan_window_alone(): void
    {
        config(['file-hosting.orphan_inactivity_minutes' => 30]);

        $user = User::factory()->create();

        // Not expired, and last part activity only 2 min ago — actively uploading.
        $active = $this->makeUpload(
            $user,
            FileUpload::STATUS_PENDING,
            Carbon::now()->addDay(),
            Carbon::now()->subMinutes(2),
        );

        $this->artisan('file-hosting:prune-stale-uploads')->assertSuccessful();

        $this->assertSame(FileUpload::STATUS_PENDING, $active->refresh()->status);
    }

    private function makeUpload(User $user, string $status, Carbon $expiresAt, ?Carbon $updatedAt = null): FileUpload
    {
        $upload = FileUpload::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'disk' => 'local',
            'path' => Str::lower(Str::random(40)),
            's3_upload_id' => null,
            'strategy' => FileUpload::STRATEGY_SINGLE,
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
