<?php

namespace App\Domain\FileHosting\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canView(): bool
    {
        return true;
    }

    public function canMutate(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::Editor => true,
            self::Viewer => false,
        };
    }

    public function canManageMembers(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Editor, self::Viewer => false,
        };
    }

    public function canArchiveWorkspace(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Roles that may be assigned via invite (not owner).
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Editor, self::Viewer];
    }
}
