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
            'vtual.content_classification.ai.max_tokens' => 4_000,
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
    public function valid_json_response_resolves_both_layers(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();
        $this->assertTrue($ai->enabled());

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'is_music' => true,
                        'category' => 'cover',
                        'detail' => 'てにをは - ヴィラン',
                        'confidence' => 0.9,
                    ])]],
                ],
            ], 200),
        ]);

        $result = $ai->classifyContent([
            'external_id' => 'vid1',
            'title' => 'ヴィラン (cover)',
            'duration_seconds' => 234,
        ]);

        $this->assertNotNull($result);
        $this->assertTrue($result['is_music']);
        $this->assertSame('cover', $result['category']->value);
        $this->assertSame('てにをは - ヴィラン', $result['detail']);
        $this->assertSame(0.9, $result['confidence']);

        // Cached per external_id — second call hits no HTTP.
        $again = $ai->classifyContent(['external_id' => 'vid1', 'title' => 'ヴィラン (cover)']);
        $this->assertTrue($again['is_music']);
        $this->assertSame('cover', $again['category']->value);
    }

    #[Test]
    public function not_music_verdict_returns_a_null_category(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'is_music' => false,
                        'category' => null,
                        'detail' => null,
                        'confidence' => 0.93,
                    ])]],
                ],
            ], 200),
        ]);

        $result = $ai->classifyContent(['external_id' => 'vid-game', 'title' => 'Minecraft Hardcore Ep 1']);

        $this->assertNotNull($result);
        $this->assertFalse($result['is_music']);
        $this->assertNull($result['category']);

        // A "not music" verdict is still cached — the pool must not re-ask.
        $again = $ai->classifyContent(['external_id' => 'vid-game', 'title' => 'Minecraft Hardcore Ep 1']);
        $this->assertFalse($again['is_music']);
    }

    #[Test]
    public function music_verdict_without_a_category_is_still_a_verdict(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'is_music' => true,
                        'category' => null,
                        'confidence' => 0.8,
                    ])]],
                ],
            ], 200),
        ]);

        $result = $ai->classifyContent(['external_id' => 'vid-music-nocat', 'title' => '星を編む']);

        $this->assertNotNull($result);
        $this->assertTrue($result['is_music']);
        $this->assertNull($result['category']);

        // The sub-category-only convenience view has nothing to report here.
        $this->assertNull($ai->classifyMusicContent(['external_id' => 'vid-music-nocat', 'title' => '星を編む']));
    }

    #[Test]
    public function a_missing_is_music_flag_is_treated_as_a_malformed_answer(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        $ai = $this->makeClassifier();

        // Guessing "false" here would wrongly stamp non_music onto the row.
        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['category' => 'cover', 'confidence' => 0.9])]],
                ],
            ], 200),
        ]);

        $this->assertNull($ai->classifyContent(['external_id' => 'vid-noflag', 'title' => '星を編む']));
    }

    #[Test]
    public function signal_fields_are_fed_into_the_user_prompt(): void
    {
        $this->bindAiConnection(self::ENDPOINT);
        $this->configureAiKnobs();

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'is_music' => true,
                        'category' => 'cover',
                        'confidence' => 0.9,
                    ])]],
                ],
            ], 200),
        ]);

        $this->makeClassifier()->classifyContent([
            'external_id' => 'vid-signals',
            'title' => 'KING (COVER)',
            'category_id' => '22',
            'topic_categories' => ['https://en.wikipedia.org/wiki/Music'],
            'tags' => ['hololive', 'cover'],
        ]);

        Http::assertSent(function ($request): bool {
            $user = $request->data()['messages'][1]['content'] ?? '';
            $system = $request->data()['messages'][0]['content'] ?? '';

            return str_contains($user, 'Platform category id: 22')
                && str_contains($user, 'Platform topics: https://en.wikipedia.org/wiki/Music')
                && str_contains($user, 'Uploader tags: hololive, cover')
                && str_contains($system, 'is_music');
        });
    }

    #[Test]
    public function connection_timeout_overrides_the_stage_setting(): void
    {
        $this->bindAiConnection(self::ENDPOINT, timeoutSeconds: 42);
        $this->configureAiKnobs();

        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['is_music' => true, 'category' => 'cover', 'confidence' => 0.9])]]],
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

        $payload = json_encode(['is_music' => true, 'category' => 'cover', 'detail' => 'てにをは - ヴィラン', 'confidence' => 0.95]);

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

        $payload = json_encode(['is_music' => true, 'category' => 'singing_stream', 'detail' => null, 'confidence' => 0.88]);

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

        $payload = json_encode(['is_music' => true, 'category' => 'original', 'confidence' => 0.91]);

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

        // A reasoning model burns the budget on reasoning tokens and then returns
        // an empty `content`; the floor is what keeps the JSON coming.
        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['is_music' => true, 'category' => 'cover', 'confidence' => 0.9])]]],
            ], 200),
        ]);

        $this->makeClassifier()->classifyMusicContent(['external_id' => 'vid-floor', 'title' => '星を編む']);

        Http::assertSent(function ($request): bool {
            return $request->url() === self::ENDPOINT
                && ($request->data()['max_tokens'] ?? null) === 256;
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
                    ['message' => ['content' => json_encode(['is_music' => true, 'category' => 'gameplay', 'confidence' => 0.99])]],
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
                    ['message' => ['content' => json_encode(['is_music' => true, 'category' => 'original', 'confidence' => 0.3])]],
                ],
            ], 200),
        ]);

        $this->assertNull($ai->classifyMusicContent(['external_id' => 'vid5', 'title' => '星を編む']));
    }
}
