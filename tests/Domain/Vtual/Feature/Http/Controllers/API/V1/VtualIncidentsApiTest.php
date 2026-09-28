<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

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
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Staff triage queue over HTTP.
 *
 * The endpoint is read + triage only: no create, no delete. Both are load
 * bearing — an incident nobody can fabricate by hand stays trustworthy, and an
 * incident that can be deleted is not an audit of an integration having been
 * broken. This suite pins those, plus the permission split between watching the
 * queue and closing an entry in it.
 */
class VtualIncidentsApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

    private Platform $youtube;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activations = app(ProductActivationService::class);
        app(PlatformService::class)->seedDefaults();
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->channel = $this->seedChannel();
    }

    #[Test]
    public function admin_lists_incidents_with_subject_and_row_actions(): void
    {
        $admin = $this->admin();
        $incident = $this->incident(IncidentKind::Websub, IncidentSeverity::Critical, [
            'platform_id' => $this->youtube->id,
            'channel_id' => $this->channel->id,
        ]);

        $rows = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertSame((string) $incident->id, $row['id']);
        $this->assertSame('websub', $row['kind']);
        $this->assertSame('critical', $row['severity']);
        $this->assertSame('open', $row['status']);
        $this->assertSame('YouTube WebSub', $row['kind_label']);
        $this->assertSame('youtube', $row['platform_key']);
        $this->assertSame('incident-channel', $row['channel_handle']);
        $this->assertSame('Hub subscribe HTTP 503', $row['detail']);
        $this->assertSame(['http_status' => 503], $row['context']);
        $this->assertSame(3, $row['occurrences']);

        $keys = $this->actionKeys($row);
        $this->assertContains('acknowledge', $keys);
        $this->assertContains('resolve', $keys);
        $this->assertContains('link', $keys);
    }

    #[Test]
    public function the_payload_never_leaks_the_fingerprint(): void
    {
        $admin = $this->admin();
        $this->incident();

        $row = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertOk()
            ->json('data.0');

        // The fingerprint is an internal grouping key: exposing it invites a
        // client to build one by hand and collide with the recorder.
        $this->assertArrayNotHasKey('fingerprint', $row);
        $this->assertArrayNotHasKey('acknowledged_by_id', $row);
        $this->assertArrayNotHasKey('resolved_by_id', $row);
    }

    #[Test]
    public function resolve_action_is_hidden_once_the_incident_is_resolved(): void
    {
        $admin = $this->admin();
        $this->incident(IncidentKind::Websub, IncidentSeverity::Critical, [
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
        ]);

        $row = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertOk()
            ->json('data.0');

        $keys = $this->actionKeys($row);

        $this->assertNotContains('acknowledge', $keys);
        $this->assertNotContains('resolve', $keys);
        $this->assertSame('resolved', $row['status']);
    }

    #[Test]
    public function a_credential_incident_links_to_the_credentials_screen(): void
    {
        $admin = $this->admin();
        $credential = app(PlatformCredentialService::class)->create(
            $admin,
            $this->youtube,
            [
                'label' => 'yt-incident-link',
                'type' => 'api_key',
                'secret' => ['api_key' => 'incident-link-key'],
            ],
        );

        $this->incident(IncidentKind::Credential, IncidentSeverity::Critical, [
            'platform_id' => $this->youtube->id,
            'credential_id' => $credential->id,
        ]);

        $row = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertOk()
            ->json('data.0');

        $link = $this->actionByKey($row, 'link');

        $this->assertNotNull($link);
        $this->assertSame('vtual.platform-credentials.platform', $link['route']);
        $this->assertSame((string) $this->youtube->id, $link['params']['platformId']);
    }

    #[Test]
    public function a_subjectless_incident_offers_no_link(): void
    {
        $admin = $this->admin();
        $this->incident(IncidentKind::PlatformHttp, IncidentSeverity::Warning, [
            'title' => 'YouTube API quota exhausted',
        ]);

        $row = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertOk()
            ->json('data.0');

        $this->assertNotContains('link', $this->actionKeys($row));
    }

    #[Test]
    public function filters_narrow_the_queue(): void
    {
        $admin = $this->admin();
        $open = $this->incident(IncidentKind::Websub, IncidentSeverity::Critical, [
            'title' => 'WebSub subscribe failed',
            'channel_id' => $this->channel->id,
        ]);
        $resolved = $this->incident(IncidentKind::Credential, IncidentSeverity::Warning, [
            'title' => 'Credential rejected',
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $byStatus = $this->ids($this->actingAsJwt($admin)->getJson('/api/v1/vtual/admin/incidents?status=resolved')->assertOk());
        $this->assertSame([(string) $resolved->id], $byStatus);

        $bySeverity = $this->ids($this->actingAsJwt($admin)->getJson('/api/v1/vtual/admin/incidents?severity=critical')->assertOk());
        $this->assertSame([(string) $open->id], $bySeverity);

        $byKind = $this->ids($this->actingAsJwt($admin)->getJson('/api/v1/vtual/admin/incidents?kind=credential')->assertOk());
        $this->assertSame([(string) $resolved->id], $byKind);

        $bySearch = $this->ids($this->actingAsJwt($admin)->getJson('/api/v1/vtual/admin/incidents?q=rejected')->assertOk());
        $this->assertSame([(string) $resolved->id], $bySearch);

        $byChannel = $this->ids($this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents?channel_id='.$this->channel->id)
            ->assertOk());
        $this->assertSame([(string) $open->id], $byChannel);
    }

    #[Test]
    public function a_date_window_is_inclusive_of_both_ends(): void
    {
        $admin = $this->admin();
        $this->incident(IncidentKind::Websub, IncidentSeverity::Critical, [
            'last_seen_at' => now()->subDays(10),
        ]);
        $recent = $this->incident(IncidentKind::Websub, IncidentSeverity::Warning, [
            'title' => 'WebSub renew failed',
            'last_seen_at' => now(),
        ]);

        $today = now()->toDateString();

        $ids = $this->ids($this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents?from='.$today.'&to='.$today)
            ->assertOk());

        $this->assertSame([(string) $recent->id], $ids);
    }

    #[Test]
    public function an_unknown_filter_value_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents?status=nonsense')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents?kind=nonsense')
            ->assertStatus(422)
            ->assertJsonValidationErrors('kind');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents?to=2026-01-01&from=2026-02-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    #[Test]
    public function paged_listing_returns_the_pagination_envelope(): void
    {
        $admin = $this->admin();

        foreach (range(1, 3) as $i) {
            $this->incident(IncidentKind::Ingestion, IncidentSeverity::Warning, ['title' => 'Fetch failed '.$i]);
        }

        $body = $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents?page=1&per_page=2')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $body['items']);
        $this->assertSame(3, $body['pagination']['total']);
    }

    #[Test]
    public function show_returns_one_incident_with_its_subject(): void
    {
        $admin = $this->admin();
        $incident = $this->incident(IncidentKind::Websub, IncidentSeverity::Critical, [
            'platform_id' => $this->youtube->id,
            'channel_id' => $this->channel->id,
        ]);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents/'.$incident->id)
            ->assertOk()
            ->assertJsonPath('data.id', (string) $incident->id)
            ->assertJsonPath('data.channel_display_name', 'Incident Channel')
            ->assertJsonPath('data.platform_name', 'YouTube');
    }

    #[Test]
    public function show_on_an_unknown_id_is_not_found(): void
    {
        $this->actingAsJwt($this->admin())
            ->getJson('/api/v1/vtual/admin/incidents/'.Str::uuid7())
            ->assertNotFound();
    }

    #[Test]
    public function acknowledge_stamps_the_acting_user(): void
    {
        $admin = $this->admin();
        $incident = $this->incident();

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/admin/incidents/'.$incident->id.'/status', ['status' => 'acknowledged'])
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged')
            ->assertJsonPath('data.acknowledged_by.id', (string) $admin->id);

        $fresh = $incident->fresh();
        $this->assertNotNull($fresh->acknowledged_at);
        $this->assertSame($admin->id, $fresh->acknowledged_by);
        $this->assertNull($fresh->resolved_at);
    }

    #[Test]
    public function resolve_stamps_the_acting_user(): void
    {
        $admin = $this->admin();
        $incident = $this->incident();

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/admin/incidents/'.$incident->id.'/status', ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_by.id', (string) $admin->id);

        $fresh = $incident->fresh();
        $this->assertNotNull($fresh->resolved_at);
        $this->assertSame($admin->id, $fresh->resolved_by);
    }

    #[Test]
    public function reopening_by_hand_clears_the_triage_stamps_but_keeps_the_history(): void
    {
        $admin = $this->admin();
        $incident = $this->incident(IncidentKind::Websub, IncidentSeverity::Critical, [
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
            'occurrences' => 4,
        ]);

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/admin/incidents/'.$incident->id.'/status', ['status' => 'open'])
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.resolved_at', null);

        $fresh = $incident->fresh();
        $this->assertNull($fresh->resolved_at);
        $this->assertNull($fresh->resolved_by);
        // Reopening is triage, not amnesia.
        $this->assertSame(4, $fresh->occurrences);
        $this->assertNotNull($fresh->first_seen_at);
    }

    #[Test]
    public function acknowledging_a_resolved_incident_drops_the_fix_claim(): void
    {
        $admin = $this->admin();
        $incident = $this->incident(IncidentKind::Websub, IncidentSeverity::Critical, [
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
        ]);

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/admin/incidents/'.$incident->id.'/status', ['status' => 'acknowledged'])
            ->assertOk()
            ->assertJsonPath('data.resolved_at', null);

        $this->assertNull($incident->fresh()->resolved_by);
    }

    #[Test]
    public function an_unknown_status_is_rejected(): void
    {
        $admin = $this->admin();
        $incident = $this->incident();

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/vtual/admin/incidents/'.$incident->id.'/status', ['status' => 'closed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(IncidentStatus::Open, $incident->fresh()->status);
    }

    #[Test]
    public function viewing_does_not_grant_the_power_to_close(): void
    {
        $viewer = $this->userWithRole('User');
        $viewer->givePermissionTo('view vtual incident');
        $this->activations->activateImplicit($viewer, 'Vtual');

        $incident = $this->incident();

        $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'open');

        $this->actingAsJwt($viewer)
            ->patchJson('/api/v1/vtual/admin/incidents/'.$incident->id.'/status', ['status' => 'resolved'])
            ->assertForbidden();

        $this->assertSame(IncidentStatus::Open, $incident->fresh()->status);
    }

    #[Test]
    public function a_viewer_without_manage_sees_no_mutation_actions(): void
    {
        $viewer = $this->userWithRole('User');
        $viewer->givePermissionTo('view vtual incident');
        $this->activations->activateImplicit($viewer, 'Vtual');
        $this->incident(IncidentKind::Websub, IncidentSeverity::Critical, [
            'channel_id' => $this->channel->id,
        ]);

        $row = $this->actingAsJwt($viewer)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertOk()
            ->json('data.0');

        $keys = $this->actionKeys($row);

        $this->assertNotContains('acknowledge', $keys);
        $this->assertNotContains('resolve', $keys);
        // Navigating to the broken subject is the whole point of the queue.
        $this->assertContains('link', $keys);
    }

    #[Test]
    public function a_user_without_the_permission_cannot_read_the_queue(): void
    {
        $user = $this->userWithRole('User');
        $this->activations->activateImplicit($user, 'Vtual');
        $this->incident();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertForbidden();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/admin/incidents/'.VtualIncident::query()->sole()->id)
            ->assertForbidden();
    }

    #[Test]
    public function a_guest_cannot_read_the_queue(): void
    {
        $this->getJson('/api/v1/vtual/admin/incidents')->assertUnauthorized();
    }

    #[Test]
    public function a_suspended_vtual_activation_closes_the_queue(): void
    {
        $admin = $this->admin();
        $activation = $this->activations->find($admin->id, 'Vtual');
        $this->assertNotNull($activation);
        $this->activations->suspend($activation, 'test');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertForbidden();
    }

    #[Test]
    public function the_queue_lazily_activates_vtual_for_a_fresh_admin(): void
    {
        // Vtual is an implicit product: an Admin who never registered through
        // the activation hook must still reach the triage queue, otherwise the
        // ops surface is unreachable exactly when something is broken.
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/admin/incidents')
            ->assertOk();

        $this->assertDatabaseHas('product_activations', [
            'user_id' => $admin->id,
            'product_key' => 'Vtual',
        ]);
    }

    /**
     * @return list<string>
     */
    private function actionKeys(array $row): array
    {
        return array_map(
            static fn (array $action): string => (string) ($action['key'] ?? ''),
            $row['actions'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actionByKey(array $row, string $key): ?array
    {
        foreach ($row['actions'] ?? [] as $action) {
            if (($action['key'] ?? null) === $key) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function ids(TestResponse $response): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['id'],
            $response->json('data'),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function incident(
        IncidentKind $kind = IncidentKind::Websub,
        IncidentSeverity $severity = IncidentSeverity::Critical,
        array $overrides = [],
    ): VtualIncident {
        $incident = new VtualIncident(array_merge([
            'fingerprint' => sha1($kind->value.'|'.uniqid('', true)),
            'kind' => $kind,
            'severity' => $severity,
            'status' => IncidentStatus::Open,
            'title' => 'YouTube WebSub subscribe failed',
            'detail' => 'Hub subscribe HTTP 503',
            'context' => ['http_status' => 503],
            'occurrences' => 3,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ], $overrides));

        $incident->save();

        return $incident;
    }

    private function seedChannel(): Channel
    {
        $creator = Creator::query()->create([
            'slug' => 'incident-api-'.Str::lower(Str::random(8)),
            'display_name' => 'Incident API Owner',
            'status' => EntityStatus::Active,
        ]);

        return Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC'.substr(md5($creator->slug), 0, 22),
            'handle' => 'incident-channel',
            'display_name' => 'Incident Channel',
            'status' => ChannelStatus::Active,
        ]);
    }

    private function admin(): User
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        return $admin;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' IncidentApi',
            'email' => strtolower($role).'-incident-api-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
