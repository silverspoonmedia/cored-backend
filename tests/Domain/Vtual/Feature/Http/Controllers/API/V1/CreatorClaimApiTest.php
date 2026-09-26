<?php

namespace Tests\Domain\Vtual\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorClaimService;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Claim queue API: `claims/mine` (owner, paginated) and `claims/pending`
 * (staff, paginated) both carry the shared `items` + `pagination` envelope
 * with row actions; the staff queue never exposes the owner bio token.
 */
class CreatorClaimApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorClaimService $claims;

    private CreatorService $creators;

    private Platform $youtube;

    private User $admin;

    private ProductActivationService $activations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->claims = app(CreatorClaimService::class);
        $this->creators = app(CreatorService::class);
        $this->activations = app(ProductActivationService::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        config([
            'vtual.claim.accept_anyway_enabled' => true,
            'vtual.claim.verify_cooldown_seconds' => 60,
            'vtual.claim.max_attempts' => 3,
            'vtual.claim.ttl_hours' => 24,
            'vtual.claim.max_pending_per_user' => 5,
        ]);

        // Activation hooks reach the WebSub hub — keep every request in-process.
        Http::fake(['pubsubhubbub.appspot.com/*' => Http::response('', 202)]);
    }

    #[Test]
    public function mine_returns_paginated_envelope_with_owner_actions(): void
    {
        $owner = $this->activatedUser('User');
        $this->startClaim($owner, 'Alpha Orphan');
        $this->startClaim($owner, 'Beta Orphan');

        $other = $this->activatedUser('User');
        $this->startClaim($other, 'Gamma Orphan');

        $page1 = $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/claims/mine?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.has_more', true)
            ->json('data');

        $this->assertCount(1, $page1['items']);

        $actions = collect($page1['items'][0]['actions'])->pluck('key')->all();
        $this->assertContains('verify', $actions);
        $this->assertContains('cancel', $actions);
        // Owner is not staff: the destructive staff shortcut stays hidden.
        $this->assertNotContains('accept_anyway', $actions);

        // Owner sees their own bio token; the other claimant's claim never leaks.
        $this->assertArrayHasKey('token', $page1['items'][0]);

        $page2 = $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/claims/mine?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.pagination.has_more', false)
            ->json('data');

        $this->assertCount(1, $page2['items']);
        $this->assertNotSame($page1['items'][0]['id'], $page2['items'][0]['id']);
    }

    #[Test]
    public function mine_clamps_per_page_to_fifty(): void
    {
        $owner = $this->activatedUser('User');
        $this->startClaim($owner, 'Clamp Orphan');

        $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/claims/mine?per_page=999')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 50);
    }

    #[Test]
    public function mine_excludes_closed_claims(): void
    {
        $owner = $this->activatedUser('User');
        $claim = $this->startClaim($owner, 'Closed Orphan');
        $this->claims->cancel($owner, $claim->id);

        $this->actingAsJwt($owner)
            ->getJson('/api/v1/vtual/claims/mine')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonCount(0, 'data.items');
    }

    #[Test]
    public function pending_requires_manage_any_permission(): void
    {
        $user = $this->activatedUser('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/vtual/claims/pending')
            ->assertForbidden();
    }

    #[Test]
    public function pending_returns_staff_envelope_with_staff_actions_and_no_token(): void
    {
        $owner = $this->activatedUser('User');
        $this->startClaim($owner, 'Staff Queue Orphan');

        $payload = $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/vtual/claims/pending?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->json('data');

        $this->assertCount(1, $payload['items']);

        $item = $payload['items'][0];
        $actions = collect($item['actions'])->pluck('key')->all();

        // Staff can force-verify or jump to the profile, but never mutate a
        // claim they do not own (verify/cancel stay owner-only).
        $this->assertContains('accept_anyway', $actions);
        $this->assertContains('view_profile', $actions);
        $this->assertNotContains('verify', $actions);
        $this->assertNotContains('cancel', $actions);

        // Hygiene: the staff queue is a queue, not a token dump.
        $this->assertArrayNotHasKey('token', $item);
    }

    #[Test]
    public function pending_hides_accept_anyway_when_disabled(): void
    {
        config(['vtual.claim.accept_anyway_enabled' => false]);

        $owner = $this->activatedUser('User');
        $this->startClaim($owner, 'Disabled Orphan');

        $payload = $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/vtual/claims/pending')
            ->assertOk()
            ->json('data');

        $actions = collect($payload['items'][0]['actions'])->pluck('key')->all();
        $this->assertNotContains('accept_anyway', $actions);
    }

    private function startClaim(User $user, string $orphanName): CreatorClaim
    {
        $orphan = $this->creators->createOrphan($this->admin, ['display_name' => $orphanName]);
        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $orphan->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC'.substr(md5($orphanName.uniqid()), 0, 22),
            'handle' => Str::slug($orphanName),
            'display_name' => $orphanName,
            'status' => ChannelStatus::Pending,
        ]);

        return $this->claims->start($user, $orphan->id, $channel->id);
    }

    private function activatedUser(string $role): User
    {
        $user = $this->userWithRole($role);
        $this->activations->activateImplicit($user, 'Vtual');

        return $user;
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' ClaimApi',
            'email' => strtolower($role).'-claimapi-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
