<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\PlatformVideoDtoMapper;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Uploader tags are the one classification signal that only exists on the
 * platform payload — the program pass reads them at hydrate time and the AI
 * audit reads them from the stored row. Until they were persisted, `--refresh`
 * and the audit drainer were blind to a signal hydrate already had in hand.
 *
 * Tags are internal: they must never reach ContentResource /
 * PublicContentResource (asserted in VtualPublicApiTest).
 */
class ContentTagsPersistenceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ContentService $contents;

    private CreatorService $creators;

    private PlatformVideoDtoMapper $mapper;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->contents = app(ContentService::class);
        $this->creators = app(CreatorService::class);
        $this->mapper = app(PlatformVideoDtoMapper::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
    }

    #[Test]
    public function mapper_forwards_uploader_tags_onto_the_upsert_payload(): void
    {
        $dto = new PlatformVideoDto(
            platform: PlatformKey::Youtube,
            externalId: 'vid-tags',
            channelExternalId: 'UCabc',
            title: 'KING (COVER)',
            description: null,
            thumbnailUrl: null,
            url: 'https://youtube.com/watch?v=vid-tags',
            categoryId: null,
            status: 'uploaded',
            publishedAt: null,
            startedAt: null,
            endedAt: null,
            durationSeconds: null,
            viewCount: null,
            concurrentViewers: null,
            tags: ['Vtuber', '歌ってみた', 'supercell'],
        );

        $payload = $this->mapper->toUpsertPayload($dto);

        $this->assertSame(['Vtuber', '歌ってみた', 'supercell'], $payload['tags']);
    }

    #[Test]
    public function upsert_persists_trimmed_deduplicated_tags(): void
    {
        $channel = $this->seedChannel();

        $content = $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-tags',
            'title' => 'KING (COVER)',
            // Whitespace, duplicates, non-strings and blanks all normalize away.
            'tags' => ['  Vtuber ', 'Vtuber', '歌ってみた', '', '   ', 12, null],
        ]);

        $fresh = $content->fresh();

        // Case is preserved — tag vocabulary matching is case-insensitive, and
        // the raw label is the readable one.
        $this->assertSame(['Vtuber', '歌ってみた'], $fresh->tags);
    }

    #[Test]
    public function upsert_treats_empty_or_invalid_tag_lists_as_no_tags(): void
    {
        $channel = $this->seedChannel();

        $content = $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-no-tags',
            'title' => 'Untagged',
            'tags' => ['', '   ', 7, null],
        ]);

        $this->assertNull($content->fresh()->tags);
    }

    #[Test]
    public function the_stored_tag_list_is_capped(): void
    {
        $channel = $this->seedChannel();

        $tags = [];
        for ($i = 0; $i < 60; $i++) {
            $tags[] = 'tag-'.$i;
        }

        $content = $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-many-tags',
            'title' => 'Tagged',
            'tags' => $tags,
        ]);

        $stored = $content->fresh()->tags;

        // A hostile or unusually chatty payload must not bloat the row.
        $this->assertCount(40, $stored);
        $this->assertSame('tag-0', $stored[0]);
        $this->assertSame('tag-39', $stored[39]);
    }

    #[Test]
    public function rehydrate_clears_stale_tags(): void
    {
        $channel = $this->seedChannel();

        $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-stale-tags',
            'title' => 'Stale',
            'tags' => ['cover'],
        ]);

        // Upstream dropped the tags: the signal must go with them, or the audit
        // would classify from a label the platform no longer carries.
        $this->contents->upsertFromPrimary($channel, [
            'external_id' => 'vid-stale-tags',
            'title' => 'Stale',
        ]);

        $row = Content::query()
            ->where('channel_id', $channel->id)
            ->where('external_id', 'vid-stale-tags')
            ->firstOrFail();

        $this->assertNull($row->tags);
    }

    private function seedChannel(): Channel
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Tag Creator']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC'.substr(md5('tags-'.uniqid()), 0, 22),
            'handle' => 'tag-creator',
            'display_name' => 'Tag Creator',
            'status' => ChannelStatus::Pending,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Tag',
            'email' => strtolower($role).'-tag-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
