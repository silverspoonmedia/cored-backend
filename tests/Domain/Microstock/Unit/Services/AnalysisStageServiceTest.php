<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\AnalysisStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AnalysisStageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_result_from_thinking_sets_generated_and_ai_raw(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFile($user);
        $metadata = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'status' => MetadataStatus::Pending,
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'output' => ['raw' => ['vision' => true]],
                'stage_metrics' => [['stage' => 'vision']],
            ],
        ]);

        $billing = Mockery::mock(AiBillingService::class);
        $billing->shouldReceive('settleSuccessfulAnalysis')->once();
        $this->app->instance(AiBillingService::class, $billing);

        $service = app(AnalysisStageService::class);
        $service->composeResultFromThinking($metadata->refresh(), [
            'title' => 'Final',
            'keywords' => ['one'],
            'description' => 'Desc',
            'category' => 'Illustration',
            'raw' => ['thinking' => true],
            'stage_metrics' => [['stage' => 'thinking']],
        ]);

        $metadata->refresh();
        $this->assertSame(MetadataStatus::Generated, $metadata->status);
        $this->assertSame('Final', $metadata->title);
        $this->assertSame(AnalysisStageStatus::Ok->value, $metadata->ai_result['status']);
        $this->assertSame(['vision' => true], $metadata->ai_raw['vision']);
        $this->assertSame(['thinking' => true], $metadata->ai_raw['thinking']);
        $this->assertCount(2, $metadata->ai_raw['stage_metrics']);
    }

    public function test_mark_thinking_failed_leaves_title_empty(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFile($user);
        $metadata = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'status' => MetadataStatus::Pending,
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'output' => [
                    'title' => 'Draft',
                    'keywords' => ['x'],
                    'raw' => ['vision' => true],
                ],
                'stage_metrics' => [],
            ],
        ]);

        app(AnalysisStageService::class)->markThinkingFailed($metadata, 'thinking died');

        $metadata->refresh();
        $this->assertSame(MetadataStatus::Failed, $metadata->status);
        $this->assertNull($metadata->title);
        $this->assertSame(AnalysisStageStatus::Failed->value, $metadata->ai_thinking['status']);
        $this->assertSame(AnalysisStageStatus::Failed->value, $metadata->ai_result['status']);
    }

    public function test_mark_vision_failed_skips_thinking(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFile($user);
        $metadata = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'status' => MetadataStatus::Pending,
        ]);

        app(AnalysisStageService::class)->markVisionFailed($metadata, 'vision died');

        $metadata->refresh();
        $this->assertSame(AnalysisStageStatus::Skipped->value, $metadata->ai_thinking['status']);
        $this->assertTrue(app(AnalysisStageService::class)->isTerminal($metadata));
    }

    private function makeFile(User $user): HostedFile
    {
        $workspace = Workspace::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Test WS',
            'kind' => WorkspaceKind::Team,
            'status' => 'active',
        ]);

        return HostedFile::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'folder_id' => null,
            'name' => 'test.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => 'test/path',
            'visibility' => 'private',
        ]);
    }
}
