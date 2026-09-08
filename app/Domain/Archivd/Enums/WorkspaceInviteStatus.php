<?php

namespace App\Domain\Archivd\Enums;

enum WorkspaceInviteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
