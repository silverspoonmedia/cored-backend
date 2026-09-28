<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Enums\IncidentStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Events\VtualIncidentChanged;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Models\VtualIncident;
use App\Domain\Vtual\Services\PlatformCredentialService;
use App\Domain\Vtual\Services\PlatformService;
use App\Domain\Vtual\Support\VtualIncidentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The recorder is the only writer of `vtual_incidents` and the only place that
 * decides grouping and reopen semantics. This suite pins those rules.
 *
 * Subjects are real rows: `platform_id` / `channel_id` / `credential_id` are
 * foreign keys, and the recorder must stay honest about that (an incident that
 * cannot point at its subject is not triageable).
 */
class VtualIncidentRecorderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private VtualIncidentRecorder $recorder;

    private Platform $youtube;

    private Channel $channel;

    private PlatformCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        app(PlatformService::class)->seedDefaults();
        $this->recorder = app(VtualIncidentRecorder::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->channel = $this->seedChannel();
        $this->credential = $this->seedCredential();
        config(['vtual.incidents.enabled' => true]);
    }

    #[Test]
    public function record_creates_an_open_incident_with_one_occurrence(): void
    {
        $incident = $this->recordOnce();

        $this->assertInstanceOf(VtualIncident::class, $incident);
        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertSame(IncidentSeverity::Critical, $incident->severity);
        $this->assertSame(IncidentKind::Websub, $incident->kind);
        $this->assertSame(1, $incident->occurrences);
        $this->assertSame((string) $this->channel->id, $incident->channel_id);
        $this->assertSame(503, $incident->context['http_status']);
        $this->assertNotNull($incident->first_seen_at);
        $this->assertNotNull($incident->last_seen_at);
    }

    #[Test]
    public function repeated_failures_group_into_one_row_and_count_occurrences(): void
    {
        $this->recordOnce();
        $this->recordOnce();
        $incident = $this->recordOnce();

        $this->assertSame(1, VtualIncident::query()->count());
        $this->assertSame(3, $incident?->occurrences);
    }

    #[Test]
    public function volatile_numbers_in_the_detail_do_not_split_the_group(): void
    {
        // 503 vs 500 is the same incident; the normalizer folds the number away.
        $this->recorder->record(
            kind: IncidentKind::Websub,
            severity: IncidentSeverity::Critical,
            title: 'YouTube WebSub subscribe failed',
            detail: 'Hub subscribe HTTP 503',
            channelId: (string) $this->channel->id,
        );

        $this->recorder->record(
            kind: IncidentKind::Websub,
            severity: IncidentSeverity::Critical,
            title: 'YouTube WebSub subscribe failed',
            detail: 'Hub subscribe HTTP 500',
            channelId: (string) $this->channel->id,
        );

        $this->assertSame(1, VtualIncident::query()->count());
        $this->assertSame(2, VtualIncident::query()->sole()->occurrences);
    }

    #[Test]
    public function the_same_failure_on_two_channels_is_two_incidents(): void
    {
        $other = $this->seedChannel('UCINCIDENTOTHER01');

        foreach ([$this->channel, $other] as $channel) {
            $this->recorder->record(
                kind: IncidentKind::Websub,
                severity: IncidentSeverity::Critical,
                title: 'YouTube WebSub subscribe failed',
                detail: 'Hub subscribe HTTP 503',
                channelId: (string) $channel->id,
            );
        }

        $this->assertSame(2, VtualIncident::query()->count());
    }

    #[Test]
    public function different_severity_is_the_same_incident_getting_worse(): void
    {
        $this->recorder->record(
            kind: IncidentKind::Credential,
            severity: IncidentSeverity::Warning,
            title: 'Platform credential call failed',
            credentialId: (string) $this->credential->id,
        );

        $incident = $this->recorder->record(
            kind: IncidentKind::Credential,
            severity: IncidentSeverity::Critical,
            title: 'Platform credential call failed',
            credentialId: (string) $this->credential->id,
        );

        $this->assertSame(1, VtualIncident::query()->count());
        $this->assertSame(IncidentSeverity::Critical, $incident?->severity);
        $this->assertSame(2, $incident?->occurrences);
    }

    #[Test]
    public function a_recurrence_reopens_an_acknowledged_incident_and_clears_the_stamp(): void
    {
        $incident = $this->recordOnce();
        $incident?->forceFill([
            'status' => IncidentStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by' => $this->userWithRole('Admin')->id,
        ])->save();

        $reopened = $this->recordOnce();

        $this->assertSame(IncidentStatus::Open, $reopened?->status);
        $this->assertNull($reopened?->acknowledged_at);
        $this->assertNull($reopened?->acknowledged_by);
        $this->assertSame(2, $reopened?->occurrences);
    }

    #[Test]
    public function a_recurrence_reopens_a_resolved_incident(): void
    {
        $incident = $this->recordOnce();
        $incident?->forceFill([
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
        ])->save();

        $reopened = $this->recordOnce();

        $this->assertSame(IncidentStatus::Open, $reopened?->status);
        $this->assertNull($reopened?->resolved_at);
        $this->assertNull($reopened?->resolved_by);
    }

    #[Test]
    public function resolve_closes_the_open_incident_for_the_subject(): void
    {
        $this->recordOnce();

        $resolved = $this->resolveOnce((string) $this->channel->id);

        $this->assertNotNull($resolved);
        $this->assertSame(IncidentStatus::Resolved, $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
    }

    #[Test]
    public function resolve_does_not_touch_a_different_channel(): void
    {
        $other = $this->seedChannel('UCINCIDENTOTHER02');
        $this->recordOnce();

        $this->assertNull($this->resolveOnce((string) $other->id));

        $this->assertSame(IncidentStatus::Open, VtualIncident::query()->sole()->status);
    }

    #[Test]
    public function resolve_is_a_noop_when_nothing_is_open(): void
    {
        $this->assertNull($this->resolveOnce((string) $this->channel->id));
    }

    #[Test]
    public function resolve_keeps_a_resolved_row_untouched(): void
    {
        $incident = $this->recordOnce();
        $incident?->forceFill(['status' => IncidentStatus::Resolved, 'resolved_at' => now()->subDay()])->save();
        $stamp = $incident?->fresh()?->resolved_at;

        $this->assertNull($this->resolveOnce((string) $this->channel->id));

        $this->assertSame(
            $stamp?->toIso8601String(),
            VtualIncident::query()->sole()->resolved_at?->toIso8601String(),
        );
    }

    #[Test]
    public function secrets_in_the_context_are_redacted_before_write(): void
    {
        config(['errorlog.redact_keys' => ['secret', 'token', 'authorization']]);

        $incident = $this->recorder->record(
            kind: IncidentKind::Credential,
            severity: IncidentSeverity::Critical,
            title: 'Platform credential authentication failed',
            context: [
                'secret' => 'super-secret-value',
                'nested' => ['token' => 'tok-123', 'safe' => 'visible'],
            ],
            credentialId: (string) $this->credential->id,
        );

        $this->assertSame('[redacted]', $incident?->context['secret']);
        $this->assertSame('[redacted]', $incident?->context['nested']['token']);
        $this->assertSame('visible', $incident?->context['nested']['safe']);
        $this->assertStringNotContainsString('super-secret-value', json_encode($incident?->context));
    }

    #[Test]
    public function detail_is_truncated_to_the_configured_cap(): void
    {
        config(['vtual.incidents.max_detail_length' => 20]);

        $incident = $this->recorder->record(
            kind: IncidentKind::Ingestion,
            severity: IncidentSeverity::Warning,
            title: 'Content fetch failed',
            detail: str_repeat('x', 200),
        );

        $this->assertSame(21, mb_strlen((string) $incident?->detail));
    }

    #[Test]
    public function oversized_context_is_replaced_with_a_marked_preview(): void
    {
        config(['vtual.incidents.max_context_json_length' => 80]);

        $incident = $this->recorder->record(
            kind: IncidentKind::Ingestion,
            severity: IncidentSeverity::Warning,
            title: 'Content fetch failed',
            context: ['blob' => str_repeat('y', 500)],
        );

        $this->assertTrue($incident?->context['_truncated'] ?? false);
        $this->assertArrayHasKey('preview', $incident?->context ?? []);
    }

    #[Test]
    public function a_blank_title_is_rejected(): void
    {
        $this->assertNull($this->recorder->record(
            kind: IncidentKind::Ingestion,
            severity: IncidentSeverity::Warning,
            title: '   ',
        ));

        $this->assertSame(0, VtualIncident::query()->count());
    }

    #[Test]
    public function recording_is_disabled_by_the_tier_b_setting(): void
    {
        config(['vtual.incidents.enabled' => false]);

        $this->assertNull($this->recordOnce());
        $this->assertSame(0, VtualIncident::query()->count());

        // resolve() honours the same switch.
        $this->assertNull($this->resolveOnce((string) $this->channel->id));
    }

    #[Test]
    public function record_fires_a_realtime_change_with_ids_and_enums_only(): void
    {
        $this->enableBroadcast();
        Event::fake([VtualIncidentChanged::class]);

        $this->recordOnce();

        Event::assertDispatched(VtualIncidentChanged::class, function (VtualIncidentChanged $event): bool {
            return $event->action === 'recorded'
                && $event->kind === IncidentKind::Websub->value
                && $event->severity === IncidentSeverity::Critical->value
                && $event->status === IncidentStatus::Open->value
                && $event->broadcastAs() === 'incident.changed'
                && $event->broadcastOn()[0]->name === 'private-vtual.incidents'
                && ! array_key_exists('title', $event->broadcastWith())
                && ! array_key_exists('detail', $event->broadcastWith());
        });
    }

    #[Test]
    public function no_realtime_event_is_fired_when_broadcast_is_unconfigured(): void
    {
        config(['broadcasting.default' => 'null']);
        Event::fake([VtualIncidentChanged::class]);

        $this->recordOnce();

        Event::assertNotDispatched(VtualIncidentChanged::class);
        $this->assertSame(1, VtualIncident::query()->count());
    }

    private function recordOnce(): ?VtualIncident
    {
        return $this->recorder->record(
            kind: IncidentKind::Websub,
            severity: IncidentSeverity::Critical,
            title: 'YouTube WebSub subscribe failed',
            detail: 'Hub subscribe HTTP 503',
            context: ['http_status' => 503],
            platformId: (string) $this->youtube->id,
            channelId: (string) $this->channel->id,
        );
    }

    /**
     * Mirrors `recordOnce()`: a recovery path must name the same subject triple
     * it recorded under, or `resolve()` matches nothing.
     */
    private function resolveOnce(string $channelId): ?VtualIncident
    {
        return $this->recorder->resolve(
            kind: IncidentKind::Websub,
            title: 'YouTube WebSub subscribe failed',
            platformId: (string) $this->youtube->id,
            channelId: $channelId,
        );
    }

    private function enableBroadcast(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);
    }

    private function seedChannel(string $externalId = 'UCINCIDENTCHANNEL1'): Channel
    {
        $creator = Creator::query()->create([
            'slug' => 'incident-'.Str::lower(Str::random(8)),
            'display_name' => 'Incident Channel Owner',
            'status' => EntityStatus::Active,
        ]);

        return Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'handle' => 'incident-'.Str::lower(Str::random(8)),
            'display_name' => 'Incident Channel',
            'status' => ChannelStatus::Active,
        ]);
    }

    private function seedCredential(): PlatformCredential
    {
        return app(PlatformCredentialService::class)->create(
            $this->userWithRole('Admin'),
            $this->youtube,
            [
                'label' => 'incident-cred-'.Str::lower(Str::random(6)),
                'type' => PlatformCredentialType::ApiKey->value,
                'secret' => ['api_key' => 'incident-key-'.Str::random(8)],
            ],
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' IncidentRecorder',
            'email' => strtolower($role).'-incident-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
