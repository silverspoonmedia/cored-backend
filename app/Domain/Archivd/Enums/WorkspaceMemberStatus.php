<?php

namespace App\Domain\Archivd\Enums;

enum WorkspaceMemberStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Removed = 'removed';
}
