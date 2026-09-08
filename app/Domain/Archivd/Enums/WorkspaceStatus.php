<?php

namespace App\Domain\Archivd\Enums;

enum WorkspaceStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
