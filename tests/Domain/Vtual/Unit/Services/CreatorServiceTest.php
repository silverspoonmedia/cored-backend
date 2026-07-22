<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreatorServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->service = app(CreatorService::class);
    }

    #[Test]
    public function create_makes_actor_owner_and_unique_slug(): void
    {
        $user = $this->userWithRole('User');

        $a = $this->service->create($user, ['display_name' => 'Test Creator']);
        $b = $this->service->create($user, ['display_name' => 'Test Creator']);

        $this->assertSame('test-creator', $a->slug);
        $this->assertSame('test-creator-2', $b->slug);
        $this->assertSame(EntityStatus::Active, $a->status);

        $member = CreatorMember::query()
            ->where('creator_id', $a->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertSame(CreatorMemberRole::Owner, $member->role);
    }

    #[Test]
    public function one_user_can_own_multiple_creators(): void
    {
        $user = $this->userWithRole('User');

        $this->service->create($user, ['display_name' => 'Alpha']);
        $this->service->create($user, ['display_name' => 'Beta']);

        $list = $this->service->listForUser($user);

        $this->assertCount(2, $list);
    }

    #[Test]
    public function archive_sets_archived_status(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->service->create($user, ['display_name' => 'Gone']);

        $archived = $this->service->archive($user, $creator->id);

        $this->assertSame(EntityStatus::Archived, $archived->status);
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
