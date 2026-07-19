<?php

namespace App\Domain\FileHosting\Enums;

enum WorkspaceStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
