<?php

namespace App\Domain\FileHosting\Enums;

enum WorkspaceInviteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
