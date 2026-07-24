<?php

namespace App\Domain\Vtual\Enums;

enum CreatorMemberRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canView(): bool
    {
        return true;
    }

    public function canMutate(): bool
    {
        return match ($this) {
            self::Owner, self::Editor => true,
            self::Viewer => false,
        };
    }

    public function canManageMembers(): bool
    {
        return $this === self::Owner;
    }

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Editor, self::Viewer];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
