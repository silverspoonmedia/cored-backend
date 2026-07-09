<?php

namespace App\Support\DataTable\Contracts;

use App\Domain\Identity\Models\User;
use App\Support\DataTable\ActionButton;

interface RowActionResolverInterface
{
    /**
     * Resolve RBAC-filtered row actions for the given record.
     *
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array;
}
