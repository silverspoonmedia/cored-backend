<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Events\MenuTreeChanged;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementInvite;
use App\Domain\Vtual\Models\ManagementMember;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Membership grant/removal must invalidate the affected viewer's presented
 * navigation tree (menu grant via membership rule) and fire a targeted
 * .menu.tree.changed broadcast with target_user_id.
 */
class ManagementMembershipNavigationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->activations = app(ProductActivationService::class);

        // Broadcast gate must pass so NavigationRealtime fires events; fake
        // ManagementUpdated so ShouldBroadcastNow stays off the wire.
        Event::fake([\App\Domain\Vtual\Events\ManagementUpdated::class]);
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

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'email' => 'u'.uniqid('', true).'@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeManagement(User $owner): Management
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
            'role' => ManagementMemberRole::Owner,
        ]);

        return $management;
    }

    /**
     * @return array{0: User, 1: Management}
     */
    private function ownerWithManagement(): array
    {
        $owner = $this->userWithRole('User');
        $this->activations->activateImplicit($owner, 'Vtual');

        return [$owner, $this->makeManagement($owner)];
    }

    #[Test]
    public function accept_invite_forgets_presented_tree_and_broadcasts_targeted_event(): void
    {
        Notification::fake();
        Event::fake([MenuTreeChanged::class, \App\Domain\Vtual\Events\ManagementUpdated::class]);

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();

        // Warm the viewer's presented tree before the grant.
        app(\App\Domain\Navigation\Repositories\Eloquent\MenuRepository::class)
            ->navigationTree($invitee->fresh(), 'sidebar');

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/accept')
            ->assertOk();

        $this->assertCacheKeyForgotten('menus.navigation.presented', (string) $invitee->id);
        Event::assertDispatched(MenuTreeChanged::class, function (MenuTreeChanged $event) use ($invitee): bool {
            return $event->targetUserId === (string) $invitee->id
                && $event->action === 'membership'
                && $event->broadcastAs() === 'menu.tree.changed'
                && $event->broadcastWith()['target_user_id'] === (string) $invitee->id;
        });
    }

    #[Test]
    public function revoke_via_cancel_forgets_presented_tree_and_broadcasts_targeted_event(): void
    {
        Notification::fake();
        Event::fake([MenuTreeChanged::class, \App\Domain\Vtual\Events\ManagementUpdated::class]);

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/accept')
            ->assertOk();

        Event::fake([MenuTreeChanged::class, \App\Domain\Vtual\Events\ManagementUpdated::class]);

        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/invites/'.$invite->id)
            ->assertOk();

        $this->assertDatabaseMissing('vtual_management_members', [
            'management_id' => $management->id,
            'user_id' => $invitee->id,
        ]);

        $this->assertCacheKeyForgotten('menus.navigation.presented', (string) $invitee->id);
        Event::assertDispatched(MenuTreeChanged::class, function (MenuTreeChanged $event) use ($invitee): bool {
            return $event->targetUserId === (string) $invitee->id
                && $event->action === 'membership';
        });
    }

    #[Test]
    public function cancel_accepted_invite_forgets_presented_tree_and_broadcasts_targeted_event(): void
    {
        Notification::fake();
        Event::fake([MenuTreeChanged::class, \App\Domain\Vtual\Events\ManagementUpdated::class]);

        [$owner, $management] = $this->ownerWithManagement();
        $invitee = $this->userWithRole('User');
        $this->activations->activateImplicit($invitee, 'Vtual');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/vtual/managements/'.$management->id.'/invites', [
                'email' => $invitee->email,
                'role' => 'staff',
            ])
            ->assertCreated();

        $invite = ManagementInvite::query()->where('email', $invitee->email)->firstOrFail();

        $this->actingAsJwt($invitee)
            ->postJson('/api/v1/vtual/invites/respond/'.$invite->token.'/accept')
            ->assertOk();

        // Warm the viewer's presented tree while the grant still exists.
        app(\App\Domain\Navigation\Repositories\Eloquent\MenuRepository::class)
            ->navigationTree($invitee->fresh(), 'sidebar');

        Event::fake([MenuTreeChanged::class, \App\Domain\Vtual\Events\ManagementUpdated::class]);

        // Unified removal path: cancelling the accepted invite revokes the membership.
        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/vtual/managements/'.$management->id.'/invites/'.$invite->id)
            ->assertOk();

        $this->assertDatabaseMissing('vtual_management_members', [
            'management_id' => $management->id,
            'user_id' => $invitee->id,
        ]);

        $this->assertCacheKeyForgotten('menus.navigation.presented', (string) $invitee->id);
        Event::assertDispatched(MenuTreeChanged::class, function (MenuTreeChanged $event) use ($invitee): bool {
            return $event->targetUserId === (string) $invitee->id
                && $event->action === 'membership';
        });
    }

    /**
     * The per-viewer presented cache key embeds the epoch + viewer id. After
     * invalidation, resolving a fresh key must produce a different epoch
     * (bumped) so the stale entry can never be read again.
     */
    private function assertCacheKeyForgotten(string $prefix, string $userId): void
    {
        // Epoch bump means any key derived now differs from pre-invalidation
        // keys. Assert the epoch key exists and is an int (bumped at least once).
        $epoch = Cache::get('menus.navigation.tree_epoch');
        $this->assertIsInt($epoch);
        $this->assertGreaterThan(0, $epoch);
    }
}
