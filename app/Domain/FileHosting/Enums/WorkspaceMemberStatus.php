<?php

namespace App\Domain\FileHosting\Enums;

enum WorkspaceMemberStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Removed = 'removed';
}
