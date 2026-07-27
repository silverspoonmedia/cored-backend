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

    private function visionResponse(): array
    {
        return [
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
}
