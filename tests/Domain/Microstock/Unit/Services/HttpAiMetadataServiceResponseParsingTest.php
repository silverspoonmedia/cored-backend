<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Services\HttpAiMetadataService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Response parsing for OpenAI-compatible gateways (incl. SSE trailers,
 * markdown-fenced JSON, and Anthropic-style content blocks).
 */
class HttpAiMetadataServiceResponseParsingTest extends TestCase
{
    private const ENDPOINT = 'https://ai.test/v1/chat/completions';

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

    private function metadataJson(string $title = 'Test title', array $keywords = ['one', 'two']): string
    {
        return json_encode([
            'title' => $title,
            'description' => 'A description.',
            'category' => 'Objects',
            'keywords' => $keywords,
        ]);
    }

    private function openAiPayload(string $content): array
    {
        return [
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
        ];
    }

    public function test_parses_standard_openai_json_string(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->openAiPayload($this->metadataJson('Vision title', ['cat'])), 200)
                ->push($this->openAiPayload($this->metadataJson('Curated title', ['curated'])), 200),
        ]);

        $result = (new HttpAiMetadataService)->generate('data:image/jpeg;base64,xx');

        $this->assertSame('Curated title', $result['title']);
        $this->assertSame(['curated'], $result['keywords']);
    }

    public function test_parses_response_with_sse_done_trailer(): void
    {
        $body = json_encode($this->openAiPayload($this->metadataJson('SSE title', ['sse']))).'data: [DONE]';

        Http::fake([
            self::ENDPOINT => Http::response($body, 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new HttpAiMetadataService)->generate('data:image/jpeg;base64,xx');

        $this->assertSame('SSE title', $result['title']);
        $this->assertSame(['sse'], $result['keywords']);
    }

    public function test_parses_markdown_fenced_json_content(): void
    {
        $fenced = "```json\n".$this->metadataJson('Fenced title', ['fence'])."\n```";

        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->openAiPayload($fenced), 200)
                ->push($this->openAiPayload($this->metadataJson('Curated', ['ok'])), 200),
        ]);

        $result = (new HttpAiMetadataService)->generate('data:image/jpeg;base64,xx');

        $this->assertSame('Curated', $result['title']);
    }

    public function test_parses_anthropic_style_content_blocks(): void
    {
        $payload = [
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'text', 'text' => $this->metadataJson('Block title', ['block'])],
                    ],
                ],
                'finish_reason' => 'stop',
            ]],
        ];

        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($payload, 200)
                ->push($this->openAiPayload($this->metadataJson('Curated', ['ok'])), 200),
        ]);

        $result = (new HttpAiMetadataService)->generate('data:image/jpeg;base64,xx');

        $this->assertSame('Curated', $result['title']);
    }

    public function test_batch_parses_assets_wrapper(): void
    {
        $batchContent = json_encode([
            'assets' => [
                [
                    'id' => 'meta-1',
                    'title' => 'Batch one',
                    'description' => 'Desc',
                    'category' => 'Objects',
                    'keywords' => ['a', 'b'],
                ],
            ],
        ]);

        Http::fake([
            self::ENDPOINT => Http::response(
                json_encode($this->openAiPayload($batchContent)).'data: [DONE]',
                200,
            ),
        ]);

        $result = (new HttpAiMetadataService)->generateBatch([
            ['id' => 'meta-1', 'image' => 'data:image/jpeg;base64,xx'],
        ]);

        $this->assertArrayHasKey('meta-1', $result);
        $this->assertSame('Batch one', $result['meta-1']['title']);
        $this->assertSame(['a', 'b'], $result['meta-1']['keywords']);
    }

    public function test_throws_on_empty_title_or_keywords(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(
                json_encode($this->openAiPayload(json_encode(['title' => '', 'keywords' => []]))),
                200,
            ),
        ]);

        $this->expectException(MicrostockException::class);

        (new HttpAiMetadataService)->generate('data:image/jpeg;base64,xx');
    }

    public function test_throws_on_unparseable_body(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response('not json at alldata: [DONE]', 200),
        ]);

        $this->expectException(MicrostockException::class);

        (new HttpAiMetadataService)->generate('data:image/jpeg;base64,xx');
    }

    public function test_parses_full_sse_stream_chunks(): void
    {
        $meta = $this->metadataJson('SSE stream title', ['stream', 'ok']);
        $half = (int) ceil(strlen($meta) / 2);
        $chunk1 = json_encode(['choices' => [['delta' => ['content' => substr($meta, 0, $half)]]]]);
        $chunk2 = json_encode(['choices' => [['delta' => ['content' => substr($meta, $half)], 'finish_reason' => 'stop']]]);
        $body = "data: {$chunk1}\n\ndata: {$chunk2}\n\ndata: [DONE]";

        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($body, 200)
                ->push($this->openAiPayload($this->metadataJson('Curated', ['ok'])), 200),
        ]);

        $result = (new HttpAiMetadataService)->generate('data:image/jpeg;base64,xx');

        $this->assertSame('Curated', $result['title']);
    }

    public function test_request_includes_stream_false(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->openAiPayload($this->metadataJson('Vision', ['v'])), 200)
                ->push($this->openAiPayload($this->metadataJson('Curated', ['c'])), 200),
        ]);

        (new HttpAiMetadataService)->generate('data:image/jpeg;base64,xx');

        Http::assertSent(function ($request) {
            return ($request->data()['stream'] ?? null) === false;
        });
    }
}
