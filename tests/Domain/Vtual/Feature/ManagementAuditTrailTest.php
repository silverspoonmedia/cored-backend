<?php

namespace Tests\Domain\Vtual\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementInviteStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Enums\VtualEventStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorManagementInvite;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\ManagementInvite;
use App\Domain\Vtual\Models\ManagementMember;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\VtualEvent;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Management audit trail: every org-scoped mutation lands in `activity_log`
 * under log_name `vtual`, with the acting user as causer.
 *
 * Personal creator surfaces are deliberately out of scope — a vTuber editing
 * their own channel is not an org action, and logging it would bury the trail
 * that staff actually review.
 */
class ManagementAuditTrailTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->admin = $this->userWithRole('Admin');
        $this->actingAs($this->admin, 'api');
    }

    #[Test]
    public function management_lifecycle_is_logged_with_the_acting_causer(): void
    {
        $management = $this->makeManagement('Audit Org');

        $created = $this->latestActivityFor($management);
        $this->assertSame('created', $created->event);
        $this->assertSame('vtual', $created->log_name);
        $this->assertSame($this->admin->id, $created->causer_id);
        $this->assertSame('Audit Org', $created->attribute_changes['attributes']['name'] ?? null);

        $management->update(['name' => 'Audit Org Renamed']);

        $updated = $this->latestActivityFor($management);
        $this->assertSame('updated', $updated->event);
        $this->assertSame('Audit Org Renamed', $updated->attribute_changes['attributes']['name'] ?? null);
        $this->assertSame('Audit Org', $updated->attribute_changes['old']['name'] ?? null);
    }

    #[Test]
    public function management_member_changes_are_logged(): void
    {
        $management = $this->makeManagement('Members Org');
        $member = ManagementMember::query()->create([
            'management_id' => $management->id,
            'user_id' => $this->admin->id,
            'role' => ManagementMemberRole::Owner,
        ]);

        $created = $this->latestActivityFor($member);
        $this->assertSame('created', $created->event);
        $this->assertSame('vtual', $created->log_name);

        $member->update(['role' => ManagementMemberRole::Admin]);

        $updated = $this->latestActivityFor($member);
        $this->assertSame('updated', $updated->event);
        $this->assertSame('admin', $updated->attribute_changes['attributes']['role'] ?? null);
        $this->assertSame('owner', $updated->attribute_changes['old']['role'] ?? null);
    }

    #[Test]
    public function management_creator_assignment_is_logged(): void
    {
        $management = $this->makeManagement('Assignments Org');
        $creator = $this->makeCreator('Assignment Talent');

        $assignment = ManagementCreator::query()->create([
            'management_id' => $management->id,
            'creator_id' => $creator->id,
            'joined_at' => now(),
        ]);

        $created = $this->latestActivityFor($assignment);
        $this->assertSame('created', $created->event);
        $this->assertSame('vtual', $created->log_name);
        $this->assertSame($creator->id, $created->attribute_changes['attributes']['creator_id'] ?? null);
    }

    #[Test]
    public function management_invite_logs_its_lifecycle_without_the_token(): void
    {
        $management = $this->makeManagement('Invites Org');

        $invite = ManagementInvite::query()->create([
            'management_id' => $management->id,
            'email' => 'invitee@example.com',
            'role' => ManagementMemberRole::Admin,
            'token' => 'mgmt-invite-secret-token',
            'status' => ManagementInviteStatus::Pending,
            'invited_by' => $this->admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $created = $this->latestActivityFor($invite);
        $this->assertSame('created', $created->event);
        $this->assertSame('vtual', $created->log_name);
        $this->assertSame('invitee@example.com', $created->attribute_changes['attributes']['email'] ?? null);
        $this->assertArrayNotHasKey('token', $created->attribute_changes['attributes'] ?? []);

        $invite->update(['status' => ManagementInviteStatus::Accepted, 'responded_at' => now()]);

        $updated = $this->latestActivityFor($invite);
        $this->assertSame('updated', $updated->event);
        $this->assertArrayNotHasKey('token', $updated->attribute_changes['attributes'] ?? []);

        $this->assertTokenNeverAppearsInVtualActivities('mgmt-invite-secret-token');
    }

    #[Test]
    public function creator_management_invite_logs_its_lifecycle_without_the_token(): void
    {
        $management = $this->makeManagement('Creator Invites Org');
        $creator = $this->makeCreator('Invited Talent');

        $invite = CreatorManagementInvite::query()->create([
            'management_id' => $management->id,
            'email' => 'creator-invitee@example.com',
            'mode' => 'existing',
            'creator_id' => $creator->id,
            'token' => 'creator-invite-secret-token',
            'status' => ManagementInviteStatus::Pending,
            'invited_by' => $this->admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $created = $this->latestActivityFor($invite);
        $this->assertSame('created', $created->event);
        $this->assertSame('vtual', $created->log_name);
        $this->assertArrayNotHasKey('token', $created->attribute_changes['attributes'] ?? []);

        $this->assertTokenNeverAppearsInVtualActivities('creator-invite-secret-token');
    }

    #[Test]
    public function vtual_event_changes_are_logged(): void
    {
        $management = $this->makeManagement('Events Org');

        $event = VtualEvent::query()->create([
            'management_id' => $management->id,
            'slug' => 'audit-event',
            'title' => 'Audit Event',
            'starts_at' => now()->addWeek(),
            'timezone' => 'Asia/Jakarta',
            'status' => VtualEventStatus::Scheduled,
        ]);

        $created = $this->latestActivityFor($event);
        $this->assertSame('created', $created->event);
        $this->assertSame('vtual', $created->log_name);
        $this->assertSame('Audit Event', $created->attribute_changes['attributes']['title'] ?? null);

        $event->update(['is_featured' => true]);

        $updated = $this->latestActivityFor($event);
        $this->assertSame('updated', $updated->event);
        $this->assertTrue((bool) ($updated->attribute_changes['attributes']['is_featured'] ?? false));
    }

    #[Test]
    public function personal_creator_surfaces_stay_out_of_the_management_audit(): void
    {
        $creator = $this->makeCreator('Solo Talent');
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => Platform::query()->where('key', 'youtube')->firstOrFail()->id,
            'external_id' => 'UCsolo-'.Str::lower(Str::random(6)),
            'handle' => 'solo-talent',
            'display_name' => 'Solo Talent',
            'status' => ChannelStatus::Active,
        ]);

        Content::query()->create([
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'platform_id' => $channel->platform_id,
            'external_id' => 'solo-vod',
            'title' => 'Solo VOD',
            'type' => ContentType::Vod,
            'status' => ContentStatus::Uploaded,
            'published_at' => now(),
        ]);

        $this->assertSame(0, Activity::query()->where('log_name', 'vtual')->count());
    }

    private function makeManagement(string $name): Management
    {
        return Management::query()->create([
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => $name,
            'status' => EntityStatus::Active,
        ]);
    }

    private function makeCreator(string $name): Creator
    {
        return Creator::query()->create([
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'display_name' => $name,
            'status' => EntityStatus::Active,
        ]);
    }

    private function latestActivityFor(Model $subject): Activity
    {
        return Activity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->latest('id')
            ->firstOrFail();
    }

    private function assertTokenNeverAppearsInVtualActivities(string $token): void
    {
        $activities = Activity::query()->where('log_name', 'vtual')->get();

        $this->assertNotEmpty($activities);

        foreach ($activities as $activity) {
            $encoded = json_encode([
                'attribute_changes' => $activity->attribute_changes?->toArray() ?? [],
                'properties' => $activity->properties?->toArray() ?? [],
            ]);
            $this->assertStringNotContainsString($token, (string) $encoded);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' Audit',
            'email' => strtolower($role).'-audit-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
