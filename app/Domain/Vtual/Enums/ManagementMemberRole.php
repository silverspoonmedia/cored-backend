<?php

namespace App\Domain\Vtual\Enums;

enum ManagementMemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function canView(): bool
    {
        return true;
    }

    public function canMutate(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Staff => false,
        };
    }

    public function canManageMembers(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Staff => false,
        };
    }

    public function canAssignCreators(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Staff => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Staff];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
