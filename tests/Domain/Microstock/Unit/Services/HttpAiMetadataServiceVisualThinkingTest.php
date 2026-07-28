<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Microstock\Services\HttpAiMetadataService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpAiMetadataServiceVisualThinkingTest extends TestCase
{
    private const ENDPOINT = 'https://ai.test/v1/chat/completions';

    private const IMAGE = 'data:image/jpeg;base64,xx';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'microstock.ai.http_retries' => 0,
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

    public function test_generate_sends_image_on_thinking_call(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->visionResponse(), 200)
                ->push($this->thinkingResponse('Girl running illustration', ['girl', 'running']), 200),
        ]);

        (new HttpAiMetadataService)->generate(self::IMAGE);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::ENDPOINT) {
                return false;
            }

            $messages = $request['messages'] ?? [];
            if (count($messages) < 2) {
                return false;
            }

            $user = $messages[1]['content'] ?? null;
            if (! is_array($user)) {
                return false;
            }

            foreach ($user as $block) {
                if (($block['type'] ?? '') === 'image_url') {
                    return ($block['image_url']['url'] ?? '') === self::IMAGE;
                }
            }

            return false;
        });
    }

    public function test_visual_thinking_falls_back_to_text_only_when_multimodal_fails(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->visionResponse(), 200)
                ->push(fn () => throw new ConnectionException('visual timeout A'))
                ->push(fn () => throw new ConnectionException('visual timeout B'))
                ->push($this->thinkingResponse('Text-only curated', ['text', 'only']), 200),
        ]);

        $result = (new HttpAiMetadataService)->generate(self::IMAGE);

        $this->assertSame('Text-only curated', $result['title']);
        $this->assertSame(['text', 'only'], $result['keywords']);
    }

    public function test_generate_calls_vision_before_thinking(): void
    {
        $order = [];

        Http::fake([
            self::ENDPOINT => function ($request) use (&$order) {
                $order[] = $request->data()['model'] ?? '';

                if (count($order) === 1) {
                    return Http::response($this->visionResponse(), 200);
                }

                return Http::response($this->thinkingResponse('After vision', ['ok']), 200);
            },
        ]);

        (new HttpAiMetadataService)->generate(self::IMAGE);

        $this->assertSame(['vision', 'thinking'], $order);
    }

    public function test_generate_returns_stage_metrics_for_vision_and_thinking(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->visionResponse('vision-model'), 200)
                ->push($this->thinkingResponse('Curated title', ['girl', 'run'], 'thinking-model'), 200),
        ]);

        $result = (new HttpAiMetadataService)->generate(self::IMAGE);

        $this->assertArrayHasKey('stage_metrics', $result);
        $metrics = $result['stage_metrics'];
        $this->assertCount(2, $metrics);
        $this->assertSame('vision', $metrics[0]['stage']);
        $this->assertSame('ok', $metrics[0]['status']);
        $this->assertSame('vision-model', $metrics[0]['resolved_model']);
        $this->assertTrue($metrics[0]['multimodal']);
        $this->assertSame('thinking', $metrics[1]['stage']);
        $this->assertSame('ok', $metrics[1]['status']);
        $this->assertSame('thinking-model', $metrics[1]['resolved_model']);
        $this->assertTrue($metrics[1]['multimodal']);
    }

    public function test_curate_draft_returns_stage_metrics(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->thinkingResponse('Curated title', ['girl', 'run'], 'thinking-model'), 200),
        ]);

        $result = (new HttpAiMetadataService)->curateDraft(
            'Draft title',
            ['draft'],
            null,
            '',
            '',
            self::IMAGE,
        );

        $this->assertArrayHasKey('stage_metrics', $result);
        $this->assertCount(1, $result['stage_metrics']);
        $this->assertSame('thinking', $result['stage_metrics'][0]['stage']);
        $this->assertTrue($result['stage_metrics'][0]['multimodal']);
    }

    private function visionResponse(string $model = 'vision'): array
    {
        return [
            'model' => $model,
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
            'choices' => [[
                'message' => ['content' => json_encode([
                    'title' => 'Wrong boy karate draft',
                    'keywords' => ['boy', 'karate'],
                    'description' => 'Boy doing karate',
                    'category' => 'Illustration',
                ])],
            ]],
        ];
    }

    /**
     * @param  list<string>  $keywords
     */
    private function thinkingResponse(string $title, array $keywords, string $model = 'thinking'): array
    {
        return [
            'model' => $model,
            'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 80, 'total_tokens' => 280],
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
}
