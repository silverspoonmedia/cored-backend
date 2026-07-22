<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\ExternalLinkKind;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Services\CreatorService;
use App\Domain\Vtual\Services\ExternalLinkService;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ExternalLinkServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ExternalLinkService $links;

    private CreatorService $creators;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->links = app(ExternalLinkService::class);
        $this->creators = app(CreatorService::class);
    }

    #[Test]
    public function owner_can_crud_links(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'Link Owner']);

        $link = $this->links->create($user, $creator->id, [
            'label' => 'Twitter',
            'url' => 'https://x.com/example',
            'kind' => ExternalLinkKind::Social->value,
        ]);

        $this->assertSame('Twitter', $link->label);
        $this->assertSame('https://x.com/example', $link->url);
        $this->assertSame(ExternalLinkKind::Social, $link->kind);

        $listed = $this->links->listForCreator($user, $creator->id);
        $this->assertCount(1, $listed);

        $updated = $this->links->update($user, $link->id, [
            'label' => 'X',
            'kind' => ExternalLinkKind::Website->value,
        ]);
        $this->assertSame('X', $updated->label);
        $this->assertSame(ExternalLinkKind::Website, $updated->kind);

        $this->links->delete($user, $link->id);
        $this->assertCount(0, $this->links->listForCreator($user, $creator->id));
    }

    #[Test]
    public function viewer_member_cannot_mutate_links(): void
    {
        $owner = $this->userWithRole('User');
        $viewer = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Shared']);

        CreatorMember::query()->create([
            'creator_id' => $creator->id,
            'user_id' => $viewer->id,
            'role' => CreatorMemberRole::Viewer,
        ]);

        $this->expectException(VtualException::class);

        $this->links->create($viewer, $creator->id, [
            'url' => 'https://example.com',
            'kind' => ExternalLinkKind::Other->value,
        ]);
    }

    #[Test]
    public function staff_manage_any_can_mutate_without_membership(): void
    {
        $owner = $this->userWithRole('User');
        $admin = $this->userWithRole('Admin');
        $creator = $this->creators->create($owner, ['display_name' => 'Staff Edit']);

        $link = $this->links->create($admin, $creator->id, [
            'label' => 'Staff',
            'url' => 'https://example.com/staff',
            'kind' => ExternalLinkKind::Other->value,
        ]);

        $this->assertNotNull($link->id);
        $this->assertCount(1, $this->links->listForCreator($admin, $creator->id));
    }

    #[Test]
    public function stranger_cannot_list_links(): void
    {
        $owner = $this->userWithRole('User');
        $stranger = $this->userWithRole('User');
        $creator = $this->creators->create($owner, ['display_name' => 'Private']);

        $this->links->create($owner, $creator->id, [
            'url' => 'https://example.com/secret',
        ]);

        $this->expectException(VtualException::class);

        $this->links->listForCreator($stranger, $creator->id);
    }

    #[Test]
    public function empty_url_rejected(): void
    {
        $user = $this->userWithRole('User');
        $creator = $this->creators->create($user, ['display_name' => 'No Url']);

        $this->expectException(VtualException::class);

        $this->links->create($user, $creator->id, [
            'url' => '   ',
        ]);
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
