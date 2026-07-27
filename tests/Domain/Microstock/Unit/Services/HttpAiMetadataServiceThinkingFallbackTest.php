<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Microstock\Services\HttpAiMetadataService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The `thinking` stage must degrade gracefully: retry with instructions fused
 * into the user message when the system prompt hangs the gateway, and fall
 * back to the vision draft when thinking fails entirely (never fail the asset).
 */
class HttpAiMetadataServiceThinkingFallbackTest extends TestCase
{
    private const ENDPOINT = 'https://ai.test/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'microstock.ai.vision' => [
                'endpoint' => self::ENDPOINT,
                'model' => 'vision',
                'api_key' => 'k',
                'timeout' => 30,
            ],
            'microstock.ai.thinking' => [
                'endpoint' => self::ENDPOINT,
                'model' => 'thinking',
                'api_key' => 'k',
                'timeout' => 30,
            ],
        ]);
    }

    private function visionResponse(): array
    {
        return [
            'choices' => [[
                'message' => ['content' => json_encode([
                    'title' => 'Draft vision title',
                    'keywords' => ['cat', 'lab', 'goggles'],
                    'description' => 'A cat in a lab wearing goggles',
                    'category' => 'Illustration',
                ])],
            ]],
        ];
    }

    private function thinkingResponse(string $title, array $keywords): array
    {
        return [
            'choices' => [[
                'message' => ['content' => json_encode([
                    'title' => $title,
                    'keywords' => $keywords,
                    'description' => 'Curated description',
                    'category' => 'Illustration',
                ])],
            ]],
        ];
    }

    public function test_thinking_uses_curated_result_on_happy_path(): void
    {
        // Vision OK, thinking OK (curated).
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->visionResponse(), 200)
                ->push($this->thinkingResponse('Curated title', ['curated', 'keywords']), 200),
        ]);

        $service = new HttpAiMetadataService;
        $result = $service->generate('data:image/jpeg;base64,xx');

        $this->assertSame('Curated title', $result['title']);
        $this->assertSame(['curated', 'keywords'], $result['keywords']);
        $this->assertSame('Curated description', $result['description']);
        $this->assertSame('Illustration', $result['category']);
    }

    public function test_thinking_retries_with_fused_user_when_system_prompt_hangs(): void
    {
        // Vision OK; first visual thinking attempt times out, second (fused) succeeds.
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->visionResponse(), 200)
                ->push(fn () => throw new ConnectionException('cURL error 28: Operation timed out'))
                ->push($this->thinkingResponse('Curated via fused', ['fused']), 200),
        ]);

        $service = new HttpAiMetadataService;
        $result = $service->generate('data:image/jpeg;base64,xx');

        $this->assertSame('Curated via fused', $result['title']);
        $this->assertSame(['fused'], $result['keywords']);
    }

    public function test_thinking_falls_back_to_vision_draft_when_all_attempts_fail(): void
    {
        // Vision OK; all thinking attempts time out -> keep vision draft.
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->visionResponse(), 200)
                ->push(fn () => throw new ConnectionException('timeout A'))
                ->push(fn () => throw new ConnectionException('timeout B'))
                ->push(fn () => throw new ConnectionException('timeout C'))
                ->push(fn () => throw new ConnectionException('timeout D')),
        ]);

        $service = new HttpAiMetadataService;
        $result = $service->generate('data:image/jpeg;base64,xx');

        $this->assertSame('Draft vision title', $result['title']);
        $this->assertSame(['cat', 'lab', 'goggles'], $result['keywords']);
        $this->assertSame('A cat in a lab wearing goggles', $result['description']);
        $this->assertSame('Illustration', $result['category']);
        $this->assertNull($result['raw']['thinking']);
    }
}
