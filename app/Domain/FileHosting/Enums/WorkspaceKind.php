<?php

namespace App\Domain\FileHosting\Enums;

/**
 * Workspace kind. `team` = user-created collaborative workspace (counts toward
 * owned slots). `microstock` = built-in system workspace for the Microstock
 * product (slot-exempt, provisioned implicitly per owner).
 */
enum WorkspaceKind: string
{
    case Team = 'team';
    case Microstock = 'microstock';

    public function isSystem(): bool
    {
        return $this !== self::Team;
    }
}
