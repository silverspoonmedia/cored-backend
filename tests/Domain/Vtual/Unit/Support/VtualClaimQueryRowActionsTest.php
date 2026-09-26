<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Models\Platform;
use App\Support\ListActions\Builders\VtualClaimQueryRowActions;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Row actions on the claim queue. Every action is re-checked by the endpoint it
 * points at, so this suite pins the *visibility* contract: who sees what.
 */
class VtualClaimQueryRowActionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private VtualClaimQueryRowActions $builder;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->builder = app(VtualClaimQueryRowActions::class);
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.claim.accept_anyway_enabled' => true,
            'vtual.claim.verify_cooldown_seconds' => 0,
            'vtual.claim.max_attempts' => 3,
        ]);
    }

    #[Test]
    public function closed_claims_have_no_actions(): void
    {
        [$creator, $channel] = $this->seedCreatorWithChannel();
        $owner = $this->userWithRole('User');
        $claim = $this->claim($owner, $creator, $channel, CreatorClaimStatus::Cancelled);

        $this->assertSame([], $this->builder->resolve($claim->load('creator'), $owner));
        $this->assertSame([], $this->builder->resolve($claim->load('creator'), $this->admin));
    }

    #[Test]
    public function actorless_and_foreign_models_resolve_to_no_actions(): void
    {
        [$creator, $channel] = $this->seedCreatorWithChannel();
        $owner = $this->userWithRole('User');
        $claim = $this->claim($owner, $creator, $channel);

        $this->assertSame([], $this->builder->resolve($claim, null));
        $this->assertSame([], $this->builder->resolve($creator, $this->admin));
    }

    #[Test]
    public function owner_sees_verify_cancel_and_profile(): void
    {
        [$creator, $channel] = $this->seedCreatorWithChannel();
        $owner = $this->userWithRole('User');
        $claim = $this->claim($owner, $creator, $channel);

        $keys = $this->keys($claim, $owner);

        $this->assertContains('verify', $keys);
        $this->assertContains('cancel', $keys);
        $this->assertContains('view_profile', $keys);
        $this->assertNotContains('accept_anyway', $keys);
    }

    #[Test]
    public function owner_without_verify_budget_keeps_cancel_only(): void
    {
        [$creator, $channel] = $this->seedCreatorWithChannel();
        $owner = $this->userWithRole('User');
        $claim = $this->claim($owner, $creator, $channel);
        $claim->forceFill(['attempt_count' => 3])->save();

        $keys = $this->keys($claim->fresh(), $owner);

        $this->assertNotContains('verify', $keys);
        $this->assertContains('cancel', $keys);
    }

    #[Test]
    public function staff_sees_accept_anyway_and_profile_but_never_owner_mutations(): void
    {
        [$creator, $channel] = $this->seedCreatorWithChannel();
        $owner = $this->userWithRole('User');
        $claim = $this->claim($owner, $creator, $channel);

        $keys = $this->keys($claim, $this->admin);

        $this->assertContains('accept_anyway', $keys);
        $this->assertContains('view_profile', $keys);
        $this->assertNotContains('verify', $keys);
        $this->assertNotContains('cancel', $keys);
    }

    #[Test]
    public function accept_anyway_disappears_when_the_setting_is_off(): void
    {
        config(['vtual.claim.accept_anyway_enabled' => false]);

        [$creator, $channel] = $this->seedCreatorWithChannel();
        $owner = $this->userWithRole('User');
        $claim = $this->claim($owner, $creator, $channel);

        $this->assertNotContains('accept_anyway', $this->keys($claim, $this->admin));
    }

    #[Test]
    public function profile_action_requires_a_slug(): void
    {
        [$creator, $channel] = $this->seedCreatorWithChannel();
        $creator->forceFill(['slug' => ''])->save();

        $owner = $this->userWithRole('User');
        $claim = $this->claim($owner, $creator, $channel);

        $this->assertNotContains('view_profile', $this->keys($claim->fresh(), $owner));
    }

    /**
     * @return list<string>
     */
    private function keys(CreatorClaim $claim, User $actor): array
    {
        return array_map(
            static fn ($action): string => $action->key,
            $this->builder->resolve($claim->load('creator'), $actor),
        );
    }

    /**
     * @return array{0: Creator, 1: Channel}
     */
    private function seedCreatorWithChannel(): array
    {
        $creator = Creator::query()->create([
            'slug' => 'row-action-'.Str::lower(Str::random(8)),
            'display_name' => 'Row Action Orphan',
            'status' => EntityStatus::Active,
        ]);

        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => Platform::query()->where('key', 'youtube')->firstOrFail()->id,
            'external_id' => 'UC'.substr(md5($creator->slug), 0, 22),
            'handle' => $creator->slug,
            'display_name' => $creator->display_name,
            'status' => ChannelStatus::Pending,
        ]);

        return [$creator, $channel];
    }

    private function claim(
        User $owner,
        Creator $creator,
        Channel $channel,
        CreatorClaimStatus $status = CreatorClaimStatus::Pending,
    ): CreatorClaim {
        $claim = new CreatorClaim([
            'user_id' => $owner->id,
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'token' => 'vtual-claim-rowaction',
            'status' => $status,
            'attempt_count' => 0,
            'expires_at' => now()->addDay(),
        ]);
        $claim->id = (string) Str::uuid7();
        $claim->save();

        return $claim;
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' RowAction',
            'email' => strtolower($role).'-rowaction-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
