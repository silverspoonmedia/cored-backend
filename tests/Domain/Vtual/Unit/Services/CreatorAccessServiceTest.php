<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Services\CreatorAccessService;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreatorAccessServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private CreatorAccessService $access;

    private CreatorService $creators;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->access = app(CreatorAccessService::class);
        $this->creators = app(CreatorService::class);
    }

    #[Test]
    public function member_role_ignores_staff_override(): void
    {
        $owner = $this->userWithRole('User');
        $admin = $this->userWithRole('Admin');
        $creator = $this->creators->create($owner, ['display_name' => 'Owned']);

        $this->assertSame(CreatorMemberRole::Owner, $this->access->roleFor($creator, $admin));
        $this->assertNull($this->access->memberRoleFor($creator, $admin));

        CreatorMember::query()->create([
            'creator_id' => $creator->id,
            'user_id' => $admin->id,
            'role' => CreatorMemberRole::Editor,
        ]);

        $this->assertSame(CreatorMemberRole::Editor, $this->access->memberRoleFor($creator, $admin));
        $this->assertSame(CreatorMemberRole::Owner, $this->access->roleFor($creator, $admin));
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
