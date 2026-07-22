<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentAppearanceRole;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ContentAppearance;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Primary host + appearances (Neuron/Hipotesa-style), not multi-ownership.
 */
class ContentServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private const VIDEO_ID = 'aVlelUsoFkY';

    private const NEURON_CHANNEL_ID = 'UCEMMrXP3_WeKiOunPYixMfA';

    private const HIPOTESA_CHANNEL_ID = 'UCKAV3scmhfcH5Ozc6ICZGRQ';

    private ContentService $contents;

    private CreatorService $creators;

    private Platform $youtube;

    private Platform $twitch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->contents = app(ContentService::class);
        $this->creators = app(CreatorService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->twitch = Platform::query()->where('key', 'twitch')->firstOrFail();
    }

    #[Test]
    public function upsert_from_primary_creates_content_and_primary_appearance(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');

        $content = $this->contents->upsertFromPrimary($neuron, [
            'external_id' => self::VIDEO_ID,
            'type' => ContentType::Vod->value,
            'status' => ContentStatus::Uploaded->value,
            'title' => 'Sample title',
            'url' => 'https://www.youtube.com/watch?v='.self::VIDEO_ID,
        ]);

        $this->assertSame(self::VIDEO_ID, $content->external_id);
        $this->assertSame((string) $neuron->id, (string) $content->channel_id);
        $this->assertSame((string) $neuron->creator_id, (string) $content->creator_id);
        $this->assertDatabaseCount('vtual_contents', 1);
        $this->assertDatabaseCount('vtual_content_appearances', 1);

        $appearance = ContentAppearance::query()->firstOrFail();
        $this->assertSame(ContentAppearanceRole::Primary, $appearance->role);
        $this->assertSame((string) $neuron->id, (string) $appearance->channel_id);
    }

    #[Test]
    public function upsert_same_external_id_updates_row_not_duplicate(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');

        $this->contents->upsertFromPrimary($neuron, [
            'external_id' => self::VIDEO_ID,
            'title' => 'Old',
        ]);
        $again = $this->contents->upsertFromPrimary($neuron, [
            'external_id' => self::VIDEO_ID,
            'title' => 'New',
        ]);

        $this->assertDatabaseCount('vtual_contents', 1);
        $this->assertSame('New', $again->title);
        $this->assertDatabaseCount('vtual_content_appearances', 1);
    }

    #[Test]
    public function attach_network_appearance_keeps_primary_host(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $hipotesa = $this->seedChannel(self::HIPOTESA_CHANNEL_ID, 'hipotesa', 'Hipotesa');

        $content = $this->contents->upsertFromPrimary($neuron, [
            'external_id' => self::VIDEO_ID,
            'title' => 'Neuron host',
        ]);

        $appearance = $this->contents->attachAppearance(
            $content,
            $hipotesa,
            ContentAppearanceRole::Network,
        );

        $this->assertSame(ContentAppearanceRole::Network, $appearance->role);
        $content->refresh();
        $this->assertSame((string) $neuron->id, (string) $content->channel_id);
        $this->assertDatabaseCount('vtual_content_appearances', 2);

        $forHipotesa = $this->contents->listForChannel((string) $hipotesa->id);
        $this->assertCount(1, $forHipotesa);
        $this->assertSame(self::VIDEO_ID, $forHipotesa->first()?->external_id);

        $forNeuron = $this->contents->listForChannel((string) $neuron->id);
        $this->assertCount(1, $forNeuron);
    }

    #[Test]
    public function list_for_creator_includes_appearance_only_content(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $hipotesa = $this->seedChannel(self::HIPOTESA_CHANNEL_ID, 'hipotesa', 'Hipotesa');

        $content = $this->contents->upsertFromPrimary($neuron, [
            'external_id' => self::VIDEO_ID,
        ]);
        $this->contents->attachAppearance($content, $hipotesa, ContentAppearanceRole::Network);

        $list = $this->contents->listForCreator((string) $hipotesa->creator_id);
        $this->assertCount(1, $list);
        $this->assertSame(self::VIDEO_ID, $list->first()?->external_id);
    }

    #[Test]
    public function cannot_attach_primary_role_via_attach(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $hipotesa = $this->seedChannel(self::HIPOTESA_CHANNEL_ID, 'hipotesa', 'Hipotesa');
        $content = $this->contents->upsertFromPrimary($neuron, ['external_id' => self::VIDEO_ID]);

        try {
            $this->contents->attachAppearance($content, $hipotesa, ContentAppearanceRole::Primary);
            $this->fail('Expected VtualException');
        } catch (VtualException $e) {
            $this->assertStringContainsString('setPrimary', $e->getMessage());
            $this->assertStringNotContainsString('owned by multiple', strtolower($e->getMessage()));
            $this->assertStringNotContainsString('multi-owner', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function cannot_detach_primary_host(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $content = $this->contents->upsertFromPrimary($neuron, ['external_id' => self::VIDEO_ID]);

        try {
            $this->contents->detachAppearance($content, $neuron);
            $this->fail('Expected VtualException');
        } catch (VtualException $e) {
            $this->assertStringContainsString('primary', strtolower($e->getMessage()));
            $this->assertStringNotContainsString('owned by multiple', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function detach_network_appearance_ok(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $hipotesa = $this->seedChannel(self::HIPOTESA_CHANNEL_ID, 'hipotesa', 'Hipotesa');
        $content = $this->contents->upsertFromPrimary($neuron, ['external_id' => self::VIDEO_ID]);
        $this->contents->attachAppearance($content, $hipotesa, ContentAppearanceRole::Network);

        $this->contents->detachAppearance($content, $hipotesa);

        $this->assertDatabaseCount('vtual_content_appearances', 1);
        $this->assertCount(0, $this->contents->listForChannel((string) $hipotesa->id));
    }

    #[Test]
    public function set_primary_flips_host_and_roles(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $hipotesa = $this->seedChannel(self::HIPOTESA_CHANNEL_ID, 'hipotesa', 'Hipotesa');
        $content = $this->contents->upsertFromPrimary($neuron, ['external_id' => self::VIDEO_ID]);
        $this->contents->attachAppearance($content, $hipotesa, ContentAppearanceRole::Network);

        $updated = $this->contents->setPrimary($content, $hipotesa, ContentAppearanceRole::Network);

        $this->assertSame((string) $hipotesa->id, (string) $updated->channel_id);
        $this->assertSame((string) $hipotesa->creator_id, (string) $updated->creator_id);

        $hipRole = ContentAppearance::query()
            ->where('content_id', $updated->id)
            ->where('channel_id', $hipotesa->id)
            ->firstOrFail();
        $neuRole = ContentAppearance::query()
            ->where('content_id', $updated->id)
            ->where('channel_id', $neuron->id)
            ->firstOrFail();

        $this->assertSame(ContentAppearanceRole::Primary, $hipRole->role);
        $this->assertSame(ContentAppearanceRole::Network, $neuRole->role);
        $this->assertSame(1, ContentAppearance::query()
            ->where('content_id', $updated->id)
            ->where('role', ContentAppearanceRole::Primary)
            ->count());
    }

    #[Test]
    public function attach_platform_mismatch_fails(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $content = $this->contents->upsertFromPrimary($neuron, ['external_id' => self::VIDEO_ID]);

        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Twitch Only']);
        $twitchChannel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->twitch->id,
            'external_id' => '123456',
            'handle' => 'twitcher',
            'display_name' => 'Twitcher',
            'status' => ChannelStatus::Pending,
        ]);

        $this->expectException(VtualException::class);
        $this->contents->attachAppearance($content, $twitchChannel, ContentAppearanceRole::Collaborator);
    }

    #[Test]
    public function duplicate_attach_fails(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $hipotesa = $this->seedChannel(self::HIPOTESA_CHANNEL_ID, 'hipotesa', 'Hipotesa');
        $content = $this->contents->upsertFromPrimary($neuron, ['external_id' => self::VIDEO_ID]);
        $this->contents->attachAppearance($content, $hipotesa, ContentAppearanceRole::Network);

        $this->expectException(VtualException::class);
        $this->contents->attachAppearance($content, $hipotesa, ContentAppearanceRole::Collaborator);
    }

    /**
     * Late-join: host + content first, second channel registered later.
     * No platform partner signal → no auto appearance; explicit attach only.
     */
    #[Test]
    public function late_join_channel_does_not_auto_attach_until_explicit(): void
    {
        $neuron = $this->seedChannel(self::NEURON_CHANNEL_ID, 'neuron', 'Neuron');
        $content = $this->contents->upsertFromPrimary($neuron, [
            'external_id' => self::VIDEO_ID,
            'title' => 'Neuron host title',
        ]);

        $this->assertDatabaseCount('vtual_content_appearances', 1);

        // Channel joins after content exists — registration alone must not invent appearances.
        $hipotesa = $this->seedChannel(self::HIPOTESA_CHANNEL_ID, 'hipotesa', 'Hipotesa');

        $this->assertDatabaseCount('vtual_content_appearances', 1);
        $this->assertCount(0, $this->contents->listForChannel((string) $hipotesa->id));
        $this->assertFalse(
            ContentAppearance::query()
                ->where('content_id', $content->id)
                ->where('channel_id', $hipotesa->id)
                ->exists()
        );

        $this->contents->attachAppearance($content, $hipotesa, ContentAppearanceRole::Network);

        $forHipotesa = $this->contents->listForChannel((string) $hipotesa->id);
        $forNeuron = $this->contents->listForChannel((string) $neuron->id);

        $this->assertCount(1, $forHipotesa);
        $this->assertCount(1, $forNeuron);
        $this->assertSame((string) $content->id, (string) $forHipotesa->first()?->id);
        $this->assertSame((string) $content->id, (string) $forNeuron->first()?->id);
        $this->assertSame('Neuron host title', $forHipotesa->first()?->title);
        $this->assertSame((string) $neuron->id, (string) $forHipotesa->first()?->channel_id);

        $this->assertDatabaseCount('vtual_content_appearances', 2);
        $this->assertSame(1, ContentAppearance::query()
            ->where('content_id', $content->id)
            ->where('role', ContentAppearanceRole::Primary)
            ->count());
    }

    private function seedChannel(string $externalId, string $handle, string $displayName): Channel
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => $displayName.' Creator']);

        return Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'handle' => $handle,
            'display_name' => $displayName,
            'url' => 'https://www.youtube.com/channel/'.$externalId,
            'status' => ChannelStatus::Pending,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Content',
            'email' => strtolower($role).'-content-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
