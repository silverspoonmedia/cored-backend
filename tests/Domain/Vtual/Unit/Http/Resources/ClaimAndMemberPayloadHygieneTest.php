<?php

namespace Tests\Domain\Vtual\Unit\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Http\Resources\CreatorClaimResource;
use App\Domain\Vtual\Http\Resources\CreatorMemberResource;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ClaimAndMemberPayloadHygieneTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
    }

    public function test_claim_token_is_visible_only_to_claim_owner(): void
    {
        $owner = $this->userWithRole('User');
        $admin = $this->userWithRole('Admin');
        app(ProductActivationService::class)->activateImplicit($owner, 'Vtual');
        $creator = app(CreatorService::class)->create($owner, ['display_name' => 'Token Owner']);
        $youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $channel = Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $youtube->id,
            'external_id' => 'UCtokenhygienetest123456789012',
            'handle' => 'tokenhygiene',
            'display_name' => 'Token Hygiene',
            'status' => ChannelStatus::Active,
        ]);

        $claim = new CreatorClaim([
            'user_id' => $owner->id,
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'token' => 'vtual-secret-token',
            'status' => 'pending',
            'attempt_count' => 0,
            'expires_at' => now()->addDay(),
        ]);
        $claim->save();

        $asOwner = Request::create('/');
        $asOwner->setUserResolver(fn () => $owner);
        $ownerPayload = (new CreatorClaimResource($claim))->resolve($asOwner);
        $this->assertSame('vtual-secret-token', $ownerPayload['token']);

        $asAdmin = Request::create('/');
        $asAdmin->setUserResolver(fn () => $admin);
        $adminPayload = (new CreatorClaimResource($claim))->resolve($asAdmin);
        $this->assertArrayNotHasKey('token', $adminPayload);
    }

    public function test_creator_viewer_does_not_see_other_member_emails(): void
    {
        $owner = $this->userWithRole('User');
        $viewer = $this->userWithRole('User');
        app(ProductActivationService::class)->activateImplicit($owner, 'Vtual');
        app(ProductActivationService::class)->activateImplicit($viewer, 'Vtual');
        $creator = app(CreatorService::class)->create($owner, ['display_name' => 'Email Gate']);

        $viewerMember = CreatorMember::query()->create([
            'creator_id' => $creator->id,
            'user_id' => $viewer->id,
            'role' => CreatorMemberRole::Viewer,
        ]);
        $viewerMember->load('user');

        $ownerMember = CreatorMember::query()
            ->where('creator_id', $creator->id)
            ->where('user_id', $owner->id)
            ->firstOrFail()
            ->load('user');

        $asViewer = Request::create('/');
        $asViewer->setUserResolver(fn () => $viewer);

        $ownerSeenByViewer = (new CreatorMemberResource($ownerMember))->resolve($asViewer);
        $selfSeenByViewer = (new CreatorMemberResource($viewerMember))->resolve($asViewer);

        $this->assertArrayNotHasKey('email', $ownerSeenByViewer['user'] ?? []);
        $this->assertSame($viewer->email, $selfSeenByViewer['user']['email'] ?? null);

        $asOwner = Request::create('/');
        $asOwner->setUserResolver(fn () => $owner);
        $viewerSeenByOwner = (new CreatorMemberResource($viewerMember))->resolve($asOwner);
        $this->assertSame($viewer->email, $viewerSeenByOwner['user']['email'] ?? null);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }
}
