<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Models\ManagementMember;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\ManagementService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ManagementServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ManagementService $managements;

    private CreatorService $creators;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->managements = app(ManagementService::class);
        $this->creators = app(CreatorService::class);
    }

    #[Test]
    public function create_makes_actor_owner(): void
    {
        $user = $this->userWithRole('User');
        $management = $this->managements->create($user, ['name' => 'Agency One']);

        $member = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertSame(ManagementMemberRole::Owner, $member->role);
        $this->assertSame('agency-one', $management->slug);
    }

    #[Test]
    public function assign_and_unassign_creator_tracks_left_at(): void
    {
        $user = $this->userWithRole('User');
        $management = $this->managements->create($user, ['name' => 'Agency']);
        $creator = $this->creators->create($user, ['display_name' => 'Talent']);

        $assignment = $this->managements->assignCreator($user, $management->id, $creator->id);
        $this->assertNull($assignment->left_at);
        $this->assertNotNull($assignment->joined_at);

        $left = $this->managements->unassignCreator($user, $management->id, $creator->id);
        $this->assertNotNull($left->left_at);
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
}
