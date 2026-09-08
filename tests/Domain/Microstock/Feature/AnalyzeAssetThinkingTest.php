<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Jobs\AnalyzeAssetThinking;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;
use App\Domain\Microstock\Services\VisionImageResolver;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\TestCase;

class AnalyzeAssetThinkingTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;

    public function test_thinking_success_generates_metadata(): void
    {
        $user = User::factory()->create();
        $this->grantMicrostockAiCredits($user);
        $this->bindFakeAi();
        $this->bindFakeResizer();
        $metadata = $this->metadataWithVisionOk($user);

        (new AnalyzeAssetThinking((string) $metadata->id, 'hint', null))->handle(
            app(AiMetadataService::class),
            app(VisionImageResolver::class),
            app(AnalysisStageService::class),
        );

        $metadata->refresh();
        $this->assertSame(MetadataStatus::Generated, $metadata->status);
        $this->assertSame('Curated title', $metadata->title);
    }

    public function test_thinking_raw_null_marks_failed_without_title(): void
    {
        $this->app->instance(AiMetadataService::class, new class implements AiMetadataService
        {
            public function analyzeVision(string $imageUrl, ?string $hint = null): array
            {
                return ['title' => 'x', 'keywords' => [], 'description' => '', 'category' => '', 'raw' => null, 'stage_metrics' => []];
            }

            public function generate(string $imageUrl, ?string $hint = null): array
            {
                return $this->analyzeVision($imageUrl, $hint);
            }

            public function generateBatch(array $items, ?string $hint = null): array
            {
                return [];
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
                    'title' => $title,
                    'keywords' => $keywords,
                    'description' => $description,
                    'category' => $category,
                    'raw' => null,
                    'stage_metrics' => [],
                ];
            }
        });

        $mock = \Mockery::mock(VisionImageResolver::class);
        $mock->shouldReceive('resolve')->andReturn('data:image/jpeg;base64,xx');
        $this->app->instance(VisionImageResolver::class, $mock);

        $user = User::factory()->create();
        $metadata = $this->metadataWithVisionOk($user);

        (new AnalyzeAssetThinking((string) $metadata->id, 'hint', null))->handle(
            app(AiMetadataService::class),
            app(VisionImageResolver::class),
            app(AnalysisStageService::class),
        );

        $metadata->refresh();
        $this->assertSame(MetadataStatus::Failed, $metadata->status);
        $this->assertNull($metadata->title);
        $this->assertSame(AnalysisStageStatus::Failed->value, $metadata->ai_thinking['status']);
    }

    private function metadataWithVisionOk(User $user): AssetMetadata
    {
        $workspace = Workspace::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Test WS',
            'kind' => WorkspaceKind::Team,
            'status' => 'active',
        ]);

        $file = HostedFile::query()->create([
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

        return AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'status' => MetadataStatus::Pending,
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'output' => [
                    'title' => 'Draft',
                    'keywords' => ['a'],
                    'description' => 'd',
                    'category' => 'Illustration',
                    'raw' => ['vision' => true],
                ],
                'stage_metrics' => [],
            ],
        ]);
    }

    private function bindFakeAi(): void
    {
        $this->app->instance(AiMetadataService::class, new class implements AiMetadataService
        {
            public function analyzeVision(string $imageUrl, ?string $hint = null): array
            {
                return ['title' => 'Draft', 'keywords' => ['a'], 'description' => '', 'category' => '', 'raw' => null, 'stage_metrics' => []];
            }

            public function generate(string $imageUrl, ?string $hint = null): array
            {
                return $this->analyzeVision($imageUrl, $hint);
            }

            public function generateBatch(array $items, ?string $hint = null): array
            {
                return [];
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
                    'stage_metrics' => [['stage' => 'thinking']],
                ];
            }
        });
    }

    private function bindFakeResizer(): void
    {
        $mock = \Mockery::mock(VisionImageResolver::class);
        $mock->shouldReceive('resolve')->andReturn('data:image/jpeg;base64,'.base64_encode('fake-preview'));
        $mock->shouldReceive('payloadByteLength')->andReturnUsing(fn (string $ref): int => strlen($ref));
        $this->app->instance(VisionImageResolver::class, $mock);
    }
}
