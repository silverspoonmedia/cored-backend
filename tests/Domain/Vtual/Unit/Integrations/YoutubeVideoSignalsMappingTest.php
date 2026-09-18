<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\YoutubeParts;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The music/non-music layer depends on topicDetails + snippet.tags surviving the
 * videos.list mapping — without them the classifier degrades to categoryId-only
 * and every vTuber cover filed under Gaming / People & Blogs is a false negative.
 */
class YoutubeVideoSignalsMappingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private PlatformApiGateway $gateway;

    private PlatformCredentialService $credentials;

    private Platform $youtube;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        app(PlatformService::class)->seedDefaults();
        $this->gateway = app(PlatformApiGateway::class);
        $this->credentials = app(PlatformCredentialService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
    }

    #[Test]
    public function topic_details_is_part_of_the_default_video_parts(): void
    {
        $this->assertContains('topicDetails', (array) config('vtual.youtube.default_video_parts'));
        $this->assertContains('topicDetails', YoutubeParts::forVideos());
    }

    #[Test]
    public function topic_categories_and_tags_are_mapped_onto_the_video_dto(): void
    {
        $this->activateYoutubeCredential('yt-key-signals');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    [
                        'id' => 'FdTy0ZWKYRo',
                        'snippet' => [
                            'channelId' => 'UCabc1234567890abcdefghij',
                            'title' => 'KING ( COVER ) / 橘ひなの',
                            'description' => '本家様：https://youtu.be/cm-l2h6GB8Q',
                            'categoryId' => '22',
                            'tags' => ['歌ってみた', 'Kanaria', ''],
                        ],
                        'contentDetails' => ['duration' => 'PT2M15S'],
                        'topicDetails' => [
                            'topicCategories' => [
                                'https://en.wikipedia.org/wiki/Music',
                                'https://en.wikipedia.org/wiki/Entertainment',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $videos = $this->gateway->fetchVideos(PlatformKey::Youtube, ['FdTy0ZWKYRo']);
        $video = $videos->first();

        $this->assertNotNull($video);
        $this->assertSame('22', $video->categoryId);
        $this->assertSame(
            ['https://en.wikipedia.org/wiki/Music', 'https://en.wikipedia.org/wiki/Entertainment'],
            $video->topicCategories,
        );
        // Blank tags are dropped, order is preserved.
        $this->assertSame(['歌ってみた', 'Kanaria'], $video->tags);

        // The probe serializer must expose the new signal fields.
        $array = $video->toArray();
        $this->assertSame('22', $array['category_id']);
        $this->assertSame(['https://en.wikipedia.org/wiki/Music', 'https://en.wikipedia.org/wiki/Entertainment'], $array['topic_categories']);
        $this->assertSame(['歌ってみた', 'Kanaria'], $array['tags']);
    }

    #[Test]
    public function a_video_without_topic_details_maps_empty_signals(): void
    {
        $this->activateYoutubeCredential('yt-key-no-signals');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    [
                        'id' => 'vid-plain',
                        'snippet' => [
                            'channelId' => 'UCabc1234567890abcdefghij',
                            'title' => 'Minecraft Hardcore Ep 1',
                            'categoryId' => '20',
                        ],
                        'contentDetails' => ['duration' => 'PT33M46S'],
                    ],
                ],
            ], 200),
        ]);

        $video = $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid-plain'])->first();

        $this->assertNotNull($video);
        $this->assertSame([], $video->topicCategories);
        $this->assertSame([], $video->tags);
    }

    #[Test]
    public function the_request_asks_for_topic_details(): void
    {
        $this->activateYoutubeCredential('yt-key-parts');

        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => []], 200),
        ]);

        $this->gateway->fetchVideos(PlatformKey::Youtube, ['vid-parts']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'youtube/v3/videos')
                && str_contains(urldecode($request->url()), 'topicDetails');
        });
    }

    private function activateYoutubeCredential(string $apiKey): void
    {
        $credential = $this->credentials->create($this->admin, $this->youtube, [
            'label' => 'yt-signals-'.uniqid(),
            'type' => PlatformCredentialType::ApiKey->value,
            'priority' => 100,
            'secret' => ['api_key' => $apiKey],
        ]);
        $credential->forceFill([
            'status' => PlatformCredentialStatus::Active,
            'verified_at' => now(),
        ])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Signals',
            'email' => strtolower($role).'-signals-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
