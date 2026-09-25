<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Enums\VtualEventStatus;
use App\Domain\Vtual\Events\ManagementUpdated;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementMember;
use App\Domain\Vtual\Models\VtualEvent;
use App\Domain\Vtual\Support\PublicLandingCache;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ManagementEventsApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activations = app(ProductActivationService::class);
        PublicLandingCache::flush();
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'email' => 'u'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeManagement(User $owner, ManagementMemberRole $role = ManagementMemberRole::Owner): Management
    {
        $management = Management::query()->create([
            'id' => (string) Str::uuid7(),
            'slug' => 'org-'.Str::random(8),
            'name' => 'Org '.Str::random(6),
            'status' => EntityStatus::Active,
        ]);

        ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $owner->id,
            'role' => $role,
        ]);

        return $management;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Anniversary Live',
            'description' => 'A celebration stream.',
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'ends_at' => now()->addDays(3)->addHours(3)->toIso8601String(),
            'timezone' => 'Asia/Jakarta',
        ], $overrides);
    }

    #[Test]
    public function owner_can_create_event(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $this->actingAsJwt($owner)
            ->postJson("/api/v1/vtual/managements/{$management->id}/events", $this->eventPayload())
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Anniversary Live')
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertDatabaseHas('vtual_events', [
            'management_id' => $management->id,
            'slug' => 'anniversary-live',
            'status' => VtualEventStatus::Scheduled->value,
        ]);
    }

    #[Test]
    public function staff_cannot_create_event(): void
    {
        $staff = $this->userWithRole('User');
        $this->activations->activateImplicit($staff, 'Vtual');
        $management = $this->makeManagement($staff, ManagementMemberRole::Staff);

        $this->actingAsJwt($staff)
            ->postJson("/api/v1/vtual/managements/{$management->id}/events", $this->eventPayload())
            ->assertStatus(403);

        $this->assertSame(0, VtualEvent::query()->where('management_id', $management->id)->count());
    }

    #[Test]
    public function non_member_cannot_create_event(): void
    {
        $outsider = $this->userWithRole('User');
        $this->activations->activateImplicit($outsider, 'Vtual');

        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $this->actingAsJwt($outsider)
            ->postJson("/api/v1/vtual/managements/{$management->id}/events", $this->eventPayload())
            ->assertStatus(403);
    }

    #[Test]
    public function staff_bypass_creates_event_on_any_management(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $this->actingAsJwt($admin)
            ->postJson("/api/v1/vtual/managements/{$management->id}/events", $this->eventPayload())
            ->assertCreated();
    }

    #[Test]
    public function validation_rejects_missing_title_or_start(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $this->actingAsJwt($owner)
            ->postJson("/api/v1/vtual/managements/{$management->id}/events", $this->eventPayload([
                'title' => '',
                'starts_at' => null,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'starts_at']);
    }

    #[Test]
    public function owner_can_cancel_scheduled_event(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $event = $this->makeEvent($management);

        $this->actingAsJwt($owner)
            ->deleteJson("/api/v1/vtual/managements/{$management->id}/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('vtual_events', [
            'id' => $event->id,
            'status' => VtualEventStatus::Cancelled->value,
        ]);
    }

    #[Test]
    public function ended_event_cannot_be_cancelled(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $event = $this->makeEvent($management, VtualEventStatus::Ended);

        $this->actingAsJwt($owner)
            ->deleteJson("/api/v1/vtual/managements/{$management->id}/events/{$event->id}")
            ->assertStatus(422);
    }

    #[Test]
    public function cross_management_list_scopes_to_memberships(): void
    {
        $member = $this->userWithRole('User');
        $this->activations->activateImplicit($member, 'Vtual');
        $mine = $this->makeManagement($member);
        $mineEvent = $this->makeEvent($mine);

        $otherOwner = $this->userWithRole('User');
        $other = $this->makeManagement($otherOwner);
        $this->makeEvent($other);

        $this->activations->activateImplicit($member, 'Vtual');

        $response = $this->actingAsJwt($member)
            ->getJson('/api/v1/vtual/events')
            ->assertOk();

        $titles = collect($response->json('data.items'))->pluck('title')->all();
        $this->assertContains($mineEvent->title, $titles);
        $this->assertCount(1, $titles);
    }

    #[Test]
    public function admin_sees_all_events_in_cross_management_list(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->activations->activateImplicit($admin, 'Vtual');

        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $this->makeEvent($management);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/vtual/events')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function per_management_list_requires_membership_or_staff(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $this->makeEvent($management);

        $outsider = $this->userWithRole('User');
        $this->activations->activateImplicit($outsider, 'Vtual');

        $this->actingAsJwt($outsider)
            ->getJson("/api/v1/vtual/managements/{$management->id}/events")
            ->assertStatus(403);

        $this->actingAsJwt($owner)
            ->getJson("/api/v1/vtual/managements/{$management->id}/events")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function event_save_flushes_landing_cache(): void
    {
        Cache::forever(PublicLandingCache::KEY, ['stale' => true]);

        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);

        $this->actingAsJwt($owner)
            ->postJson("/api/v1/vtual/managements/{$management->id}/events", $this->eventPayload())
            ->assertCreated();

        $this->assertNull(Cache::get(PublicLandingCache::KEY));
    }

    #[Test]
    public function owner_can_update_event_and_realtime_reports_updated(): void
    {
        $this->enableBroadcast();
        Event::fake([ManagementUpdated::class]);

        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $event = $this->makeEvent($management);

        $this->actingAsJwt($owner)
            ->patchJson("/api/v1/vtual/managements/{$management->id}/events/{$event->id}", [
                'title' => 'Rescheduled Anniversary',
                'starts_at' => now()->addDays(9)->toIso8601String(),
                'description' => 'Moved to next week.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Rescheduled Anniversary')
            ->assertJsonPath('data.description', 'Moved to next week.');

        $this->assertDatabaseHas('vtual_events', [
            'id' => $event->id,
            'title' => 'Rescheduled Anniversary',
        ]);

        Event::assertDispatched(
            ManagementUpdated::class,
            fn (ManagementUpdated $broadcast): bool => $broadcast->managementId === $management->id
                && $broadcast->kind === 'event'
                && $broadcast->refId === (string) $event->id
                && $broadcast->action === 'updated',
        );
    }

    #[Test]
    public function staff_cannot_update_event(): void
    {
        $staff = $this->userWithRole('User');
        $this->activations->activateImplicit($staff, 'Vtual');
        $management = $this->makeManagement($staff, ManagementMemberRole::Staff);
        $event = $this->makeEvent($management);

        $this->actingAsJwt($staff)
            ->patchJson("/api/v1/vtual/managements/{$management->id}/events/{$event->id}", [
                'title' => 'Staff Overreach',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('vtual_events', ['title' => 'Staff Overreach']);
    }

    #[Test]
    public function ended_event_cannot_be_updated(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $event = $this->makeEvent($management, VtualEventStatus::Ended);

        $this->actingAsJwt($owner)
            ->patchJson("/api/v1/vtual/managements/{$management->id}/events/{$event->id}", [
                'title' => 'Too Late',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function event_of_another_management_is_not_found(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $mine = $this->makeManagement($owner);

        $otherOwner = $this->userWithRole('User');
        $this->activations->activateImplicit($otherOwner, 'Vtual');
        $other = $this->makeManagement($otherOwner);
        $foreignEvent = $this->makeEvent($other);

        $this->actingAsJwt($owner)
            ->patchJson("/api/v1/vtual/managements/{$mine->id}/events/{$foreignEvent->id}", [
                'title' => 'Hijacked',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function update_validation_rejects_blank_title_and_bad_dates(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $event = $this->makeEvent($management);

        $this->actingAsJwt($owner)
            ->patchJson("/api/v1/vtual/managements/{$management->id}/events/{$event->id}", [
                'title' => '',
                'starts_at' => now()->addDays(5)->toIso8601String(),
                'ends_at' => now()->addDays(1)->toIso8601String(),
                'url' => 'not-a-url',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'ends_at', 'url']);
    }

    #[Test]
    public function member_can_read_single_event_but_outsider_cannot(): void
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $event = $this->makeEvent($management);

        $this->actingAsJwt($owner)
            ->getJson("/api/v1/vtual/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $event->id)
            ->assertJsonPath('data.management_id', (string) $management->id);

        $outsider = $this->userWithRole('User');
        $this->activations->activateImplicit($outsider, 'Vtual');

        $this->actingAsJwt($outsider)
            ->getJson("/api/v1/vtual/events/{$event->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function update_flushes_landing_cache(): void
    {
        Cache::forever(PublicLandingCache::KEY, ['stale' => true]);

        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');
        $management = $this->makeManagement($owner);
        $event = $this->makeEvent($management);

        $this->actingAsJwt($owner)
            ->patchJson("/api/v1/vtual/managements/{$management->id}/events/{$event->id}", [
                'title' => 'Refreshed Cache Event',
            ])
            ->assertOk();

        $this->assertNull(Cache::get(PublicLandingCache::KEY));
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

    private function makeEvent(Management $management, VtualEventStatus $status = VtualEventStatus::Scheduled): VtualEvent
    {
        return VtualEvent::query()->create([
            'management_id' => $management->id,
            'slug' => 'event-'.Str::random(8),
            'title' => 'Event '.Str::random(6),
            'starts_at' => now()->addDays(2),
            'status' => $status,
        ]);
    }
}
