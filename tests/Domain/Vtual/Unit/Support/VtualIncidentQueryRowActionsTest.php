<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Enums\IncidentStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\VtualIncident;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\PlatformService;
use App\Support\ListActions\Builders\VtualIncidentQueryRowActions;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Row actions on the incident queue. The endpoints behind them re-check the
 * permission, so this suite pins the *visibility* contract: which actions each
 * viewer sees on which row, and where the `link` action points.
 */
class VtualIncidentQueryRowActionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private VtualIncidentQueryRowActions $builder;

    private Platform $youtube;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        app(PlatformService::class)->seedDefaults();
        $this->builder = app(VtualIncidentQueryRowActions::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->channel = $this->seedChannel();
    }

    #[Test]
    public function actorless_and_foreign_models_resolve_to_no_actions(): void
    {
        $admin = $this->userWithRole('Admin');
        $incident = $this->incident();

        $this->assertSame([], $this->builder->resolve($incident, null));
        $this->assertSame([], $this->builder->resolve($this->youtube, $admin));
    }

    #[Test]
    public function an_open_incident_offers_acknowledge_resolve_and_link(): void
    {
        $admin = $this->userWithRole('Admin');
        $incident = $this->incident([
            'platform_id' => $this->youtube->id,
            'channel_id' => $this->channel->id,
        ]);

        $keys = $this->keys($incident, $admin);

        $this->assertContains('acknowledge', $keys);
        $this->assertContains('resolve', $keys);
        $this->assertContains('link', $keys);
    }

    #[Test]
    public function an_acknowledged_incident_drops_acknowledge_but_keeps_resolve(): void
    {
        $admin = $this->userWithRole('Admin');
        $incident = $this->incident(['status' => IncidentStatus::Acknowledged]);

        $keys = $this->keys($incident, $admin);

        $this->assertNotContains('acknowledge', $keys);
        $this->assertContains('resolve', $keys);
    }

    #[Test]
    public function a_resolved_incident_offers_no_mutation(): void
    {
        $admin = $this->userWithRole('Admin');
        $incident = $this->incident([
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $keys = $this->keys($incident, $admin);

        $this->assertNotContains('acknowledge', $keys);
        $this->assertNotContains('resolve', $keys);
    }

    #[Test]
    public function a_viewer_without_manage_gets_the_link_only(): void
    {
        $viewer = $this->userWithRole('User');
        $viewer->givePermissionTo('view vtual incident');

        $incident = $this->incident(['channel_id' => $this->channel->id]);
        $keys = $this->keys($incident, $viewer);

        $this->assertSame(['link'], $keys);
    }

    #[Test]
    public function a_viewer_without_the_permission_gets_nothing_at_all(): void
    {
        $outsider = $this->userWithRole('User');
        $incident = $this->incident(['channel_id' => $this->channel->id]);

        $this->assertSame([], $this->builder->resolve($incident, $outsider));
    }

    #[Test]
    public function a_credential_incident_links_to_the_credentials_screen(): void
    {
        $admin = $this->userWithRole('Admin');
        $credential = app(PlatformCredentialService::class)->create($admin, $this->youtube, [
            'label' => 'yt-row-action',
            'type' => 'api_key',
            'secret' => ['api_key' => 'row-action-key'],
        ]);

        $incident = $this->incident([
            'platform_id' => $this->youtube->id,
            'credential_id' => $credential->id,
        ]);

        $link = $this->actionByKey($incident, $admin, 'link');

        $this->assertNotNull($link);
        $this->assertSame('vtual.platform-credentials.platform', $link['route']);
        $this->assertSame((string) $this->youtube->id, $link['params']['platformId']);
    }

    #[Test]
    public function a_channel_incident_links_to_the_owning_profile(): void
    {
        $admin = $this->userWithRole('Admin');
        $incident = $this->incident([
            'platform_id' => $this->youtube->id,
            'channel_id' => $this->channel->id,
        ]);

        $link = $this->actionByKey($incident, $admin, 'link');

        $this->assertNotNull($link);
        $this->assertSame('vtual.profiles.show', $link['route']);
        $this->assertSame((string) $this->channel->creator_id, $link['params']['id']);
    }

    #[Test]
    public function a_subjectless_incident_has_no_link(): void
    {
        $admin = $this->userWithRole('Admin');
        $incident = $this->incident(['kind' => IncidentKind::PlatformHttp]);

        $this->assertNull($this->actionByKey($incident, $admin, 'link'));
        $this->assertSame(['acknowledge', 'resolve'], $this->keys($incident, $admin));
    }

    #[Test]
    public function a_channel_incident_without_a_loaded_creator_falls_back_to_the_credential_link(): void
    {
        $admin = $this->userWithRole('Admin');
        $credential = app(PlatformCredentialService::class)->create($admin, $this->youtube, [
            'label' => 'yt-row-action-both',
            'type' => 'api_key',
            'secret' => ['api_key' => 'row-action-key-both'],
        ]);

        // Both subjects present: the credential is the narrower fix, so it wins.
        $incident = $this->incident([
            'platform_id' => $this->youtube->id,
            'channel_id' => $this->channel->id,
            'credential_id' => $credential->id,
        ]);

        $link = $this->actionByKey($incident, $admin, 'link');

        $this->assertNotNull($link);
        $this->assertSame('vtual.platform-credentials.platform', $link['route']);
    }

    /**
     * @return list<string>
     */
    private function keys(VtualIncident $incident, User $actor): array
    {
        return array_map(
            static fn ($action): string => $action->key,
            $this->builder->resolve($this->hydrate($incident), $actor),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actionByKey(VtualIncident $incident, User $actor, string $key): ?array
    {
        foreach ($this->builder->resolve($this->hydrate($incident), $actor) as $action) {
            if ($action->key === $key) {
                return $action->toArray();
            }
        }

        return null;
    }

    /**
     * The builder reads `channel.creator_id` off a loaded relation; the API
     * controller always eager-loads it, so the unit under test must too.
     */
    private function hydrate(VtualIncident $incident): VtualIncident
    {
        return $incident->fresh()->load(['platform', 'channel', 'credential']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function incident(array $overrides = []): VtualIncident
    {
        $incident = new VtualIncident(array_merge([
            'fingerprint' => sha1('row-action|'.uniqid('', true)),
            'kind' => IncidentKind::Websub,
            'severity' => IncidentSeverity::Critical,
            'status' => IncidentStatus::Open,
            'title' => 'YouTube WebSub subscribe failed',
            'detail' => 'Hub subscribe HTTP 503',
            'context' => ['http_status' => 503],
            'occurrences' => 1,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ], $overrides));

        $incident->save();

        return $incident;
    }

    private function seedChannel(): Channel
    {
        $creator = Creator::query()->create([
            'slug' => 'row-action-'.Str::lower(Str::random(8)),
            'display_name' => 'Row Action Owner',
            'status' => EntityStatus::Active,
        ]);

        return Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC'.substr(md5($creator->slug), 0, 22),
            'handle' => 'row-action-channel',
            'display_name' => 'Row Action Channel',
            'status' => ChannelStatus::Active,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' IncidentRowAction',
            'email' => strtolower($role).'-incident-rowaction-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
