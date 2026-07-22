<?php

namespace Tests\Domain\Vtual\Unit\Enums;

use App\Domain\Vtual\Enums\CreatorMemberRole;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreatorMemberRoleTest extends TestCase
{
    #[Test]
    public function owner_and_editor_can_mutate_viewer_cannot(): void
    {
        $this->assertTrue(CreatorMemberRole::Owner->canMutate());
        $this->assertTrue(CreatorMemberRole::Editor->canMutate());
        $this->assertFalse(CreatorMemberRole::Viewer->canMutate());
    }

    #[Test]
    public function only_owner_manages_members(): void
    {
        $this->assertTrue(CreatorMemberRole::Owner->canManageMembers());
        $this->assertFalse(CreatorMemberRole::Editor->canManageMembers());
        $this->assertFalse(CreatorMemberRole::Viewer->canManageMembers());
    }
}
