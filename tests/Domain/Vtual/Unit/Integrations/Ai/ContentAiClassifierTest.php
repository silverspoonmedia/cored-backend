<?php

namespace Tests\Domain\Vtual\Unit\Integrations\Ai;

use App\Domain\Vtual\Integrations\Ai\ContentAiClassifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BindsAiConnection;
use Tests\TestCase;

class ContentAiClassifierTest extends TestCase
{
    use BindsAiConnection;

    private const ENDPOINT = 'https://ai.example.test/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeClassifier(): ContentAiClassifier
    {
        return app(ContentAiClassifier::class);
    }

    /**
     * Tier B knobs read via setting_config → config() fallback in tests.
     * The endpoint/key come from the bound AI connection, not config.
     */
    private function configureAiKnobs(bool $enabled = true): void
    {
        config([
            'vtual.content_classification.ai.enabled' => $enabled,
            'vtual.content_classification.ai.connection' => '',
            'vtual.content_classification.ai.model' => 'audit-model',
            'vtual.content_classification.ai.timeout' => 15,
            'vtual.content_classification.ai.max_tokens' => 200,
            'vtual.content_classification.ai.min_confidence' => 0.7,
            'vtual.content_classification.ai.cache_ttl_hours' => 168,
        ]);
    }

    #[Test]
    public function unconfigured_returns_null_without_http(): void
    {
        $this->bindNoAiConnection();
        $this->configureAiKnobs(enabled: false);

        $ai = $this->makeClassifier();

        $this->assertFalse($ai->enabled());
        $this->assertNull($ai->classifyMusicContent(['title' => '星を編む']));

        Http::assertNothingSent();
    }

    #[Test]
    public function enabled_without_a_bound_connection_returns_null_without_http(): void
    {
        $this->bindNoAiConnection();
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        $this->assertFalse($ai->enabled());
        $this->assertNull($ai->classifyMusicContent(['external_id' => 'vid0', 'title' => '星を編む']));

        Http::assertNothingSent();
    }

    #[Test]
    public function valid_json_response_resolves_category_and_detail(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();
        $this->assertTrue($ai->enabled());

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'category' => 'cover',
                        'detail' => 'てにをは - ヴィラン',
                        'confidence' => 0.9,
                    ])]],
                ],
            ], 200),
        ]);

        $result = $ai->classifyMusicContent([
            'external_id' => 'vid1',
            'title' => 'ヴィラン (cover)',
            'duration_seconds' => 234,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('cover', $result['category']->value);
        $this->assertSame('てにをは - ヴィラン', $result['detail']);
        $this->assertSame(0.9, $result['confidence']);

        // Cached per external_id — second call hits no HTTP.
        $again = $ai->classifyMusicContent(['external_id' => 'vid1', 'title' => 'ヴィラン (cover)']);
        $this->assertSame('cover', $again['category']->value);
    }

    #[Test]
    public function connection_timeout_overrides_the_stage_setting(): void
    {
        $this->bindAiConnection(self::ENDPOINT, timeoutSeconds: 42);
        $this->configureAiKnobs();

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['category' => 'cover', 'confidence' => 0.9])]]],
            ], 200),
        ]);

        $this->makeClassifier()->classifyMusicContent(['external_id' => 'vid-timeout', 'title' => '星を編む']);

        Http::assertSent(fn ($request): bool => $request->url() === self::ENDPOINT);
    }

    #[Test]
    public function http_failure_returns_null(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        Http::fake([
            'ai.example.test/*' => Http::response([], 500),
        ]);

        $this->assertNull($ai->classifyMusicContent(['external_id' => 'vid2', 'title' => '星を編む']));
    }

    #[Test]
    public function fenced_json_response_is_still_parsed(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        $payload = json_encode(['category' => 'cover', 'detail' => 'てにをは - ヴィラン', 'confidence' => 0.95]);

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => "```json\n{$payload}\n```"]],
                ],
            ], 200),
        ]);

        $result = $ai->classifyMusicContent(['external_id' => 'vid-fence', 'title' => 'ヴィラン (cover)']);

        $this->assertNotNull($result);
        $this->assertSame('cover', $result['category']->value);
        $this->assertSame('てにをは - ヴィラン', $result['detail']);
        $this->assertSame(0.95, $result['confidence']);
    }

    #[Test]
    public function json_wrapped_in_prose_is_still_parsed(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        $payload = json_encode(['category' => 'singing_stream', 'detail' => null, 'confidence' => 0.88]);

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => "Sure, here it is:\n{$payload}\nHope that helps."]],
                ],
            ], 200),
        ]);

        $result = $ai->classifyMusicContent(['external_id' => 'vid-prose', 'title' => '歌枠']);

        $this->assertNotNull($result);
        $this->assertSame('singing_stream', $result['category']->value);
        $this->assertNull($result['detail']);
    }

    #[Test]
    public function fenced_json_without_a_language_tag_is_still_parsed(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        $payload = json_encode(['category' => 'original', 'confidence' => 0.91]);

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => "```\n{$payload}\n```"]],
                ],
            ], 200),
        ]);

        $result = $ai->classifyMusicContent(['external_id' => 'vid-fence-plain', 'title' => 'オリジナル曲']);

        $this->assertNotNull($result);
        $this->assertSame('original', $result['category']->value);
    }

    #[Test]
    public function max_tokens_setting_is_sent_as_the_request_budget(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();
        config(['vtual.content_classification.ai.max_tokens' => 1_800]);

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['category' => 'cover', 'confidence' => 0.9])]]],
            ], 200),
        ]);

        $this->makeClassifier()->classifyMusicContent(['external_id' => 'vid-budget', 'title' => '星を編む']);

        Http::assertSent(function ($request): bool {
            return $request->url() === self::ENDPOINT
                && ($request->data()['max_tokens'] ?? null) === 1_800;
        });
    }

    #[Test]
    public function max_tokens_never_drops_below_the_floor(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();
        config(['vtual.content_classification.ai.max_tokens' => 10]);

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['category' => 'cover', 'confidence' => 0.9])]]],
            ], 200),
        ]);

        $this->makeClassifier()->classifyMusicContent(['external_id' => 'vid-floor', 'title' => '星を編む']);

        Http::assertSent(function ($request): bool {
            return $request->url() === self::ENDPOINT
                && ($request->data()['max_tokens'] ?? null) === 50;
        });
    }

    #[Test]
    public function malformed_json_content_returns_null(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'not json at all']],
                ],
            ], 200),
        ]);

        $this->assertNull($ai->classifyMusicContent(['external_id' => 'vid3', 'title' => '星を編む']));
    }

    #[Test]
    public function unknown_category_returns_null(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['category' => 'gameplay', 'confidence' => 0.99])]],
                ],
            ], 200),
        ]);

        $this->assertNull($ai->classifyMusicContent(['external_id' => 'vid4', 'title' => '星を編む']));
    }

    #[Test]
    public function low_confidence_returns_null(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['category' => 'original', 'confidence' => 0.3])]],
                ],
            ], 200),
        ]);

        $this->assertNull($ai->classifyMusicContent(['external_id' => 'vid5', 'title' => '星を編む']));
    }
}
