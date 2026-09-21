<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Microstock\Services\HttpAiMetadataService;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BindsAiConnection;
use Tests\TestCase;

class HttpAiMetadataServiceCurateDraftTest extends TestCase
{
    use BindsAiConnection;

    private const ENDPOINT = 'https://ai.test/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindAiConnection();

        config([
            'microstock.ai.http_retries' => 0,
            'microstock.ai.thinking' => [
                'endpoint' => self::ENDPOINT,
                'model' => 'thinking',
                'api_key' => 'k',
                'timeout' => 30,
            ],
        ]);
    }

    public function test_curate_draft_returns_thinking_result(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'title' => 'Curated running boy illustration',
                        'keywords' => ['boy', 'running', 'child', 'sport'],
                        'description' => 'Cartoon boy jogging.',
                        'category' => 'Illustration',
                    ])],
                ]],
            ], 200),
        ]);

        $result = (app(HttpAiMetadataService::class))->curateDraft(
            'Draft forest title',
            ['forest', 'autumn'],
            'Children doing sports',
            'Wrong draft',
            'Nature',
            'data:image/jpeg;base64,preview',
        );

        $this->assertSame('Curated running boy illustration', $result['title']);
        $this->assertSame(['boy', 'running', 'child', 'sport'], $result['keywords']);
        $this->assertSame('Illustration', $result['category']);
        $this->assertIsArray($result['raw']);

        Http::assertSent(function ($request) {
            $user = $request['messages'][1]['content'] ?? null;

            if (! is_array($user)) {
                return false;
            }

            foreach ($user as $block) {
                if (($block['type'] ?? '') === 'image_url') {
                    return true;
                }
            }

            return false;
        });
    }
}
