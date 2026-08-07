<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Services\CreatorService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CreatorServiceOwnedListTest extends TestCase
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
    public function list_owned_for_user_returns_only_owner_memberships(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('User');

        $owned = $this->service->create($owner, ['display_name' => 'Owned Profile']);
        $other = $this->service->create($owner, ['display_name' => 'Second Owned']);

        $editorUser = User::factory()->create();
        CreatorMember::query()->create([
            'creator_id' => $owned->id,
            'user_id' => $editorUser->id,
            'role' => CreatorMemberRole::Editor,
        ]);

        $list = $this->service->listOwnedForUser($owner);

        $this->assertCount(2, $list);
        $this->assertSame(
            ['Owned Profile', 'Second Owned'],
            $list->pluck('display_name')->sort()->values()->all(),
        );

        $this->assertCount(0, $this->service->listOwnedForUser($editorUser));
    }
}
