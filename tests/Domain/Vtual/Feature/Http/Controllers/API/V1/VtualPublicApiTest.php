<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VtualPublicApiTest extends TestCase
{
    use RefreshDatabase;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();

        $this->youtube = Platform::query()->create([
            'key' => 'youtube',
            'name' => 'YouTube',
            'enabled' => true,
            'supports_live' => true,
            'supports_vod' => true,
        ]);
    }

    #[Test]
    public function guest_can_load_public_directory(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 1500);

        $this->getJson('/api/v1/vtual/public/vtubers')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.slug', $creator->slug)
            ->assertJsonPath('data.items.0.display_name', 'Neon Fox')
            ->assertJsonPath('data.items.0.channels.0.platform_key', 'youtube')
            ->assertJsonPath('data.items.0.channels.0.subscriber_count', 1500)
            ->assertJsonPath('data.items.0.total_subscribers', 1500);
    }

    #[Test]
    public function directory_hides_inactive_creators_and_creators_without_public_channel(): void
    {
        $this->activeCreatorWithPublicChannel('Visible Talent', 100);

        $archived = Creator::query()->create([
            'slug' => 'archived-talent',
            'display_name' => 'Archived Talent',
            'status' => EntityStatus::Archived,
        ]);
        $this->publicChannel($archived, 200);

        $privateOnly = Creator::query()->create([
            'slug' => 'private-only',
            'display_name' => 'Private Only',
            'status' => EntityStatus::Active,
        ]);
        Channel::query()->create([
            'creator_id' => $privateOnly->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-private-only',
            'display_name' => 'Private Only',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Private,
            'subscriber_count' => 50,
        ]);

        $response = $this->getJson('/api/v1/vtual/public/vtubers')->assertOk();

        $slugs = collect($response->json('data.items'))->pluck('slug')->all();

        $this->assertSame(['visible-talent'], $slugs);
    }

    #[Test]
    public function directory_search_filters_by_display_name(): void
    {
        $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $this->activeCreatorWithPublicChannel('Azure Wolf', 200);

        $this->getJson('/api/v1/vtual/public/vtubers?search=neon')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.slug', 'neon-fox');
    }

    #[Test]
    public function guest_can_load_public_profile_without_claim_urls_or_uuid(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 1500);

        $response = $this->getJson('/api/v1/vtual/public/vtubers/'.$creator->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $creator->slug)
            ->assertJsonPath('data.display_name', 'Neon Fox')
            ->assertJsonPath('data.channels.0.platform_key', 'youtube')
            ->assertJsonPath('data.is_claimable', true)
            ->assertJsonPath('data.is_live', false)
            ->assertJsonPath('data.total_subscribers', 1500)
            ->assertJsonPath('data.total_subscribers_formatted', '1,500')
            ->assertJsonPath('data.channels.0.banner_url', null);

        $json = $response->json('data');

        $this->assertArrayNotHasKey('id', $json);
        $this->assertArrayNotHasKey('claim_process_url', $json);
        $this->assertArrayNotHasKey('claim_blocked_until', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertSame([], $json['links']);
    }

    #[Test]
    public function public_profile_404_for_archived_creator(): void
    {
        Creator::query()->create([
            'slug' => 'archived-talent',
            'display_name' => 'Archived Talent',
            'status' => EntityStatus::Archived,
        ]);

        $this->getJson('/api/v1/vtual/public/vtubers/archived-talent')
            ->assertNotFound();
    }

    #[Test]
    public function public_profile_hides_archived_channels(): void
    {
        $creator = Creator::query()->create([
            'slug' => 'neon-fox',
            'display_name' => 'Neon Fox',
            'status' => EntityStatus::Active,
        ]);
        $this->publicChannel($creator, 1500);

        Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-archived',
            'display_name' => 'Old Channel',
            'status' => ChannelStatus::Archived,
            'visibility' => ChannelVisibility::Public,
            'subscriber_count' => 10,
        ]);

        $this->getJson('/api/v1/vtual/public/vtubers/neon-fox')
            ->assertOk()
            ->assertJsonCount(1, 'data.channels');
    }

    #[Test]
    public function public_endpoints_do_not_require_auth(): void
    {
        $this->activeCreatorWithPublicChannel('Neon Fox', 100);

        // No actingAsJwt, no cookie — pure guest.
        $this->getJson('/api/v1/vtual/public/vtubers')->assertOk();
        $this->getJson('/api/v1/vtual/public/vtubers/neon-fox')->assertOk();
    }

    #[Test]
    public function directory_exposes_bio_snippet_and_live_flag_and_sorts_live_first(): void
    {
        $offline = $this->activeCreatorWithPublicChannel('Azure Wolf', 200);
        $offline->forceFill([
            'bio' => str_repeat('A', 200),
        ])->save();

        $live = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $this->liveContent($live, 'live-1');

        $items = $this->getJson('/api/v1/vtual/public/vtubers')
            ->assertOk()
            ->json('data.items');

        $this->assertSame('neon-fox', $items[0]['slug']);
        $this->assertTrue($items[0]['is_live']);
        $this->assertSame('azure-wolf', $items[1]['slug']);
        $this->assertFalse($items[1]['is_live']);
        $this->assertNotNull($items[1]['bio_snippet']);
        $this->assertTrue(str_ends_with($items[1]['bio_snippet'], '...'));
        $this->assertLessThan(200, mb_strlen($items[1]['bio_snippet']));
    }

    #[Test]
    public function public_profile_404_without_public_channel(): void
    {
        $privateOnly = Creator::query()->create([
            'slug' => 'private-only',
            'display_name' => 'Private Only',
            'status' => EntityStatus::Active,
        ]);
        Channel::query()->create([
            'creator_id' => $privateOnly->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-private-only',
            'display_name' => 'Private Only',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Private,
            'subscriber_count' => 50,
        ]);

        $this->getJson('/api/v1/vtual/public/vtubers/private-only')
            ->assertNotFound();
    }

    #[Test]
    public function public_profile_is_claimable_false_when_owned_and_exposes_links(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $user = \App\Domain\Identity\Models\User::factory()->create();
        \App\Domain\Vtual\Models\CreatorMember::query()->create([
            'creator_id' => $creator->id,
            'user_id' => $user->id,
            'role' => \App\Domain\Vtual\Enums\CreatorMemberRole::Owner,
        ]);
        $creator->externalLinks()->create([
            'label' => 'Discord',
            'url' => 'https://discord.gg/example',
            'kind' => \App\Domain\Vtual\Enums\ExternalLinkKind::Social,
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/v1/vtual/public/vtubers/neon-fox')
            ->assertOk()
            ->assertJsonPath('data.is_claimable', false)
            ->assertJsonPath('data.links.0.kind', 'social')
            ->assertJsonPath('data.links.0.label', 'Discord')
            ->assertJsonPath('data.links.0.url', 'https://discord.gg/example');

        $link = $response->json('data.links.0');
        $this->assertArrayNotHasKey('id', $link);
    }

    #[Test]
    public function public_profile_paginates_contents_with_live_first(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $channel = $creator->channels()->firstOrFail();

        for ($i = 1; $i <= 13; $i++) {
            \App\Domain\Vtual\Models\Content::query()->create([
                'creator_id' => $creator->id,
                'channel_id' => $channel->id,
                'platform_id' => $this->youtube->id,
                'external_id' => 'vod-'.$i,
                'type' => \App\Domain\Vtual\Enums\ContentType::Vod,
                'status' => \App\Domain\Vtual\Enums\ContentStatus::Uploaded,
                'title' => 'VOD '.$i,
                'published_at' => now()->subDays($i),
            ]);
        }

        $this->liveContent($creator, 'live-now');

        $page1 = $this->getJson('/api/v1/vtual/public/vtubers/neon-fox')
            ->assertOk()
            ->assertJsonPath('data.is_live', true)
            ->json('data.contents');

        $this->assertCount(12, $page1['items']);
        $this->assertSame('live', $page1['items'][0]['type']);
        $this->assertSame('live', $page1['items'][0]['status']);
        $this->assertSame('youtube', $page1['items'][0]['platform_key']);
        $this->assertSame(1, $page1['pagination']['current_page']);
        $this->assertSame(14, $page1['pagination']['total']);
        $this->assertTrue($page1['pagination']['has_more']);

        // Page 2 has the remaining VODs; is_live still true (whole-catalogue check).
        $page2 = $this->getJson('/api/v1/vtual/public/vtubers/neon-fox?page=2')
            ->assertOk()
            ->assertJsonPath('data.is_live', true)
            ->json('data.contents');

        $this->assertCount(2, $page2['items']);
        $this->assertSame(2, $page2['pagination']['current_page']);
        $this->assertFalse($page2['pagination']['has_more']);
    }

    #[Test]
    public function public_profile_contents_respect_per_page_bound(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $channel = $creator->channels()->firstOrFail();

        for ($i = 1; $i <= 5; $i++) {
            \App\Domain\Vtual\Models\Content::query()->create([
                'creator_id' => $creator->id,
                'channel_id' => $channel->id,
                'platform_id' => $this->youtube->id,
                'external_id' => 'vod-'.$i,
                'type' => \App\Domain\Vtual\Enums\ContentType::Vod,
                'status' => \App\Domain\Vtual\Enums\ContentStatus::Uploaded,
                'title' => 'VOD '.$i,
                'published_at' => now()->subDays($i),
            ]);
        }

        $payload = $this->getJson('/api/v1/vtual/public/vtubers/neon-fox?per_page=2')
            ->assertOk()
            ->json('data.contents');

        $this->assertCount(2, $payload['items']);
        $this->assertSame(2, $payload['pagination']['per_page']);
        $this->assertSame(3, $payload['pagination']['last_page']);

        // per_page clamps to max 24
        $clamped = $this->getJson('/api/v1/vtual/public/vtubers/neon-fox?per_page=99')
            ->assertOk()
            ->json('data.contents.pagination.per_page');
        $this->assertSame(24, $clamped);
    }

    #[Test]
    public function guest_can_load_public_contents_feed_without_ids(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $this->vodContent($creator, 'vod-1', 'First upload', now()->subDay());

        $response = $this->getJson('/api/v1/vtual/public/contents')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.title', 'First upload')
            ->assertJsonPath('data.items.0.platform_key', 'youtube')
            ->assertJsonPath('data.items.0.creator.slug', 'neon-fox')
            ->assertJsonPath('data.items.0.creator.display_name', 'Neon Fox')
            ->assertJsonPath('data.items.0.channel.display_name', 'Neon Fox');

        $item = $response->json('data.items.0');
        $this->assertArrayNotHasKey('id', $item);
        $this->assertArrayNotHasKey('external_id', $item);
        $this->assertArrayNotHasKey('creator_id', $item);

        $channel = $response->json('data.items.0.channel');
        $this->assertIsArray($channel);
        $this->assertArrayHasKey('avatar_url', $channel);
        $this->assertArrayNotHasKey('id', $channel);
    }

    #[Test]
    public function public_contents_hides_archived_private_and_stub_rows(): void
    {
        $visible = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $this->vodContent($visible, 'vod-visible', 'Visible VOD', now());

        $this->vodContent($visible, 'stub-row', '', now()->subHour(), url: null);

        $archived = Creator::query()->create([
            'slug' => 'archived-talent',
            'display_name' => 'Archived Talent',
            'status' => EntityStatus::Archived,
        ]);
        $archivedChannel = $this->publicChannel($archived, 50);
        $this->vodContent($archived, 'vod-archived', 'Archived VOD', now(), $archivedChannel);

        $privateOnly = Creator::query()->create([
            'slug' => 'private-only',
            'display_name' => 'Private Only',
            'status' => EntityStatus::Active,
        ]);
        $privateChannel = Channel::query()->create([
            'creator_id' => $privateOnly->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-private-only',
            'display_name' => 'Private Only',
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Private,
            'subscriber_count' => 50,
        ]);
        $this->vodContent($privateOnly, 'vod-private', 'Private VOD', now(), $privateChannel);

        $response = $this->getJson('/api/v1/vtual/public/contents')->assertOk();
        $titles = collect($response->json('data.items'))->pluck('title')->all();

        $this->assertSame(['Visible VOD'], $titles);
    }

    #[Test]
    public function public_contents_sorts_live_first_and_paginates(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $this->vodContent($creator, 'vod-old', 'Old VOD', now()->subDays(2));
        $this->liveContent($creator, 'live-now');

        $first = $this->getJson('/api/v1/vtual/public/contents?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.has_more', true)
            ->json('data.items');

        $this->assertCount(1, $first);
        $this->assertSame('live', $first[0]['type']);
        $this->assertSame('live', $first[0]['status']);

        $second = $this->getJson('/api/v1/vtual/public/contents?per_page=1&page=2')
            ->assertOk()
            ->json('data.items');

        $this->assertSame('Old VOD', $second[0]['title']);
    }

    #[Test]
    public function public_profile_exposes_channel_banner_url(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $creator->channels()->firstOrFail()->forceFill([
            'banner_url' => 'https://img.example/banner.jpg',
        ])->save();

        $this->getJson('/api/v1/vtual/public/vtubers/neon-fox')
            ->assertOk()
            ->assertJsonPath('data.channels.0.banner_url', 'https://img.example/banner.jpg');
    }

    #[Test]
    public function guest_can_load_landing_payload(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 1500);
        $this->liveContent($creator, 'live-landing');
        $this->scheduledContent($creator, 'sched-1', 'Debut stream', now()->addDay());

        $this->getJson('/api/v1/vtual/public/landing')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.event', null)
            ->assertJsonPath('data.live_now.0.title', 'Live now')
            ->assertJsonPath('data.live_now.0.creator.slug', 'neon-fox')
            ->assertJsonPath('data.schedule.0.title', 'Debut stream')
            ->assertJsonPath('data.stats.creators', 1)
            ->assertJsonPath('data.stats.channels', 1)
            ->assertJsonPath('data.stats.subscribers_total', 1500);
    }

    #[Test]
    public function landing_event_comes_from_settings_and_is_null_when_unset(): void
    {
        $this->activeCreatorWithPublicChannel('Neon Fox', 100);

        $this->getJson('/api/v1/vtual/public/landing')
            ->assertOk()
            ->assertJsonPath('data.event', null);

        app(\App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface::class)
            ->upsertMany([
                'vtual.landing.event.title' => 'Community Showcase',
                'vtual.landing.event.starts_at' => '2026-09-01T20:00:00+09:00',
                'vtual.landing.event.body' => 'Debuts and collabs.',
            ]);

        $this->getJson('/api/v1/vtual/public/landing')
            ->assertOk()
            ->assertJsonPath('data.event.title', 'Community Showcase')
            ->assertJsonPath('data.event.starts_at', '2026-09-01T20:00:00+09:00')
            ->assertJsonPath('data.event.body', 'Debuts and collabs.');
    }

    #[Test]
    public function landing_event_null_for_invalid_date(): void
    {
        $this->activeCreatorWithPublicChannel('Neon Fox', 100);

        app(\App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface::class)
            ->upsertMany([
                'vtual.landing.event.title' => 'Broken',
                'vtual.landing.event.starts_at' => 'not-a-date',
            ]);

        $this->getJson('/api/v1/vtual/public/landing')
            ->assertOk()
            ->assertJsonPath('data.event', null);
    }

    #[Test]
    public function guest_can_load_stats_live_and_schedule_endpoints(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 1500);
        $this->liveContent($creator, 'live-1');
        $this->scheduledContent($creator, 'sched-1', 'Upcoming', now()->addHours(6));

        $this->getJson('/api/v1/vtual/public/stats')
            ->assertOk()
            ->assertJsonPath('data.creators', 1)
            ->assertJsonPath('data.subscribers_total_formatted', '1,500');

        $this->getJson('/api/v1/vtual/public/live')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.type', 'live')
            ->assertJsonPath('data.items.0.status', 'live');

        $this->getJson('/api/v1/vtual/public/schedule')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Upcoming');
    }

    #[Test]
    public function schedule_excludes_past_and_untitled_rows(): void
    {
        $creator = $this->activeCreatorWithPublicChannel('Neon Fox', 100);
        $this->scheduledContent($creator, 'sched-past', 'Past stream', now()->subDay());
        $this->scheduledContent($creator, 'sched-blank', '', now()->addDay());
        $this->scheduledContent($creator, 'sched-future', 'Future stream', now()->addDay());

        $titles = $this->getJson('/api/v1/vtual/public/schedule')
            ->assertOk()
            ->json('data.items');

        $this->assertSame(['Future stream'], collect($titles)->pluck('title')->all());
    }

    #[Test]
    public function guest_can_load_public_events(): void
    {
        $management = \App\Domain\Vtual\Models\Management::query()->create([
            'slug' => 'org-alpha',
            'name' => 'Org Alpha',
            'status' => EntityStatus::Active,
        ]);

        \App\Domain\Vtual\Models\VtualEvent::query()->create([
            'management_id' => $management->id,
            'slug' => 'anniversary-live',
            'title' => 'Anniversary Live',
            'starts_at' => now()->addDays(2),
            'status' => \App\Domain\Vtual\Enums\VtualEventStatus::Scheduled->value,
        ]);
        \App\Domain\Vtual\Models\VtualEvent::query()->create([
            'management_id' => $management->id,
            'slug' => 'past-event',
            'title' => 'Past event',
            'starts_at' => now()->subDay(),
            'status' => \App\Domain\Vtual\Enums\VtualEventStatus::Ended->value,
        ]);
        \App\Domain\Vtual\Models\VtualEvent::query()->create([
            'management_id' => $management->id,
            'slug' => 'cancelled-event',
            'title' => 'Cancelled event',
            'starts_at' => now()->addDay(),
            'status' => \App\Domain\Vtual\Enums\VtualEventStatus::Cancelled->value,
        ]);

        $items = $this->getJson('/api/v1/vtual/public/events')
            ->assertOk()
            ->json('data.items');

        $this->assertSame(['Anniversary Live'], collect($items)->pluck('title')->all());
        $this->assertSame('Org Alpha', $items[0]['management']['name']);
        $this->assertSame('scheduled', $items[0]['status']);
        $this->assertArrayNotHasKey('id', $items[0]);
    }

    #[Test]
    public function landing_exposes_events_key_and_flushes_on_event_save(): void
    {
        \App\Domain\Vtual\Support\PublicLandingCache::flush();

        $this->getJson('/api/v1/vtual/public/landing')
            ->assertOk()
            ->assertJsonPath('data.events', []);

        $management = \App\Domain\Vtual\Models\Management::query()->create([
            'slug' => 'org-beta',
            'name' => 'Org Beta',
            'status' => EntityStatus::Active,
        ]);

        \App\Domain\Vtual\Models\VtualEvent::query()->create([
            'management_id' => $management->id,
            'slug' => 'debut-showcase',
            'title' => 'Debut Showcase',
            'starts_at' => now()->addDays(5),
            'status' => \App\Domain\Vtual\Enums\VtualEventStatus::Scheduled->value,
        ]);

        $payload = $this->getJson('/api/v1/vtual/public/landing')
            ->assertOk()
            ->json('data.events');

        $this->assertSame('Debut Showcase', $payload[0]['title']);
    }

    private function activeCreatorWithPublicChannel(string $name, int $subscribers): Creator
    {
        $creator = Creator::query()->create([
            'slug' => \Illuminate\Support\Str::slug($name),
            'display_name' => $name,
            'status' => EntityStatus::Active,
        ]);

        $this->publicChannel($creator, $subscribers);

        return $creator;
    }

    private function publicChannel(Creator $creator, int $subscribers): Channel
    {
        return Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-'.$creator->slug,
            'display_name' => $creator->display_name,
            'url' => 'https://youtube.com/@'.$creator->slug,
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
            'subscriber_count' => $subscribers,
        ]);
    }

    private function liveContent(Creator $creator, string $externalId): \App\Domain\Vtual\Models\Content
    {
        $channel = $creator->channels()->firstOrFail();

        return \App\Domain\Vtual\Models\Content::query()->create([
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'type' => \App\Domain\Vtual\Enums\ContentType::Live,
            'status' => \App\Domain\Vtual\Enums\ContentStatus::Live,
            'title' => 'Live now',
            'url' => 'https://youtube.com/watch?v='.$externalId,
            'published_at' => now(),
        ]);
    }

    private function scheduledContent(
        Creator $creator,
        string $externalId,
        string $title,
        \DateTimeInterface $scheduledAt,
    ): \App\Domain\Vtual\Models\Content {
        $channel = $creator->channels()->firstOrFail();

        return \App\Domain\Vtual\Models\Content::query()->create([
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'type' => \App\Domain\Vtual\Enums\ContentType::Live,
            'status' => \App\Domain\Vtual\Enums\ContentStatus::Scheduled,
            'title' => $title === '' ? null : $title,
            'url' => 'https://youtube.com/watch?v='.$externalId,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    private function vodContent(
        Creator $creator,
        string $externalId,
        string $title,
        \DateTimeInterface $publishedAt,
        ?Channel $channel = null,
        ?string $url = 'default',
    ): \App\Domain\Vtual\Models\Content {
        $channel ??= $creator->channels()->firstOrFail();

        return \App\Domain\Vtual\Models\Content::query()->create([
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'type' => \App\Domain\Vtual\Enums\ContentType::Vod,
            'status' => \App\Domain\Vtual\Enums\ContentStatus::Uploaded,
            'title' => $title === '' ? null : $title,
            'url' => $url === 'default' ? 'https://youtube.com/watch?v='.$externalId : $url,
            'published_at' => $publishedAt,
        ]);
    }
}
