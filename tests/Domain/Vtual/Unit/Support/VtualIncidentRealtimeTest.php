<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Enums\IncidentStatus;
use App\Domain\Vtual\Events\VtualIncidentChanged;
use App\Domain\Vtual\Models\VtualIncident;
use App\Domain\Vtual\Services\VtualIncidentService;
use App\Domain\Vtual\Support\VtualIncidentRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * `vtual.incidents` carries ids + enums and nothing else.
 *
 * The incident `title`/`detail` are assembled from platform error text and can
 * carry handles, URLs, or a fragment that survived redaction — and this channel
 * fans out to every staff subscriber at once. The list refetches instead, so the
 * payload shape is a contract, not an implementation detail.
 */
class VtualIncidentRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    #[Test]
    public function enabled_follows_broadcast_credentials(): void
    {
        $realtime = app(VtualIncidentRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($realtime->enabled());

        $this->enableBroadcast();
        $this->assertTrue($realtime->enabled());
    }

    #[Test]
    public function changed_carries_ids_and_enums_only(): void
    {
        Event::fake([VtualIncidentChanged::class]);
        $this->enableBroadcast();

        app(VtualIncidentRealtime::class)->changed(
            'recorded',
            'incident-uuid',
            IncidentKind::Websub,
            IncidentSeverity::Critical,
            IncidentStatus::Open,
        );

        Event::assertDispatched(VtualIncidentChanged::class, function (VtualIncidentChanged $event): bool {
            $this->assertSame('recorded', $event->action);
            $this->assertSame('incident-uuid', $event->incidentId);
            $this->assertSame('websub', $event->kind);
            $this->assertSame('critical', $event->severity);
            $this->assertSame('open', $event->status);

            $this->assertSame('incident.changed', $event->broadcastAs());
            $this->assertSame('private-vtual.incidents', $event->broadcastOn()[0]->name);

            $payload = $event->broadcastWith();
            $this->assertSame(
                ['action', 'incident_id', 'kind', 'severity', 'status'],
                array_keys($payload),
            );

            return true;
        });
    }

    #[Test]
    public function string_enums_are_accepted_so_callers_need_not_hydrate_them(): void
    {
        Event::fake([VtualIncidentChanged::class]);
        $this->enableBroadcast();

        app(VtualIncidentRealtime::class)->changed('status', 'incident-uuid', 'credential', 'warning', 'resolved');

        Event::assertDispatched(
            VtualIncidentChanged::class,
            fn (VtualIncidentChanged $event): bool => $event->kind === 'credential'
                && $event->severity === 'warning'
                && $event->status === 'resolved',
        );
    }

    #[Test]
    public function no_event_when_broadcast_is_unconfigured(): void
    {
        Event::fake([VtualIncidentChanged::class]);
        $this->disableBroadcast();

        app(VtualIncidentRealtime::class)->changed('recorded', 'incident-uuid');

        Event::assertNotDispatched(VtualIncidentChanged::class);
    }

    #[Test]
    public function a_triage_transition_pings_the_queue(): void
    {
        Event::fake([VtualIncidentChanged::class]);
        $this->enableBroadcast();

        $actor = $this->userWithRole('Admin');
        $incident = $this->incident();

        app(VtualIncidentService::class)->updateStatus($incident, IncidentStatus::Acknowledged, $actor->id);

        Event::assertDispatched(VtualIncidentChanged::class, function (VtualIncidentChanged $event) use ($incident): bool {
            return $event->action === 'status'
                && $event->incidentId === (string) $incident->id
                && $event->status === 'acknowledged'
                && ! array_key_exists('title', $event->broadcastWith())
                && ! array_key_exists('context', $event->broadcastWith());
        });
    }

    private function incident(): VtualIncident
    {
        $incident = new VtualIncident([
            'fingerprint' => sha1('realtime|'.uniqid('', true)),
            'kind' => IncidentKind::Websub,
            'severity' => IncidentSeverity::Critical,
            'status' => IncidentStatus::Open,
            'title' => 'YouTube WebSub subscribe failed',
            'detail' => 'Hub subscribe HTTP 503',
            'context' => ['http_status' => 503],
            'occurrences' => 1,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ]);
        $incident->save();

        return $incident;
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

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' IncidentRealtime',
            'email' => strtolower($role).'-incident-realtime-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
