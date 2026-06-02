<?php

namespace Tests\Concerns;

use Database\Seeders\RolePermissionSeeder;

trait SeedsRolesAndPermissions
{
    protected function seedRolesAndPermissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }
}
