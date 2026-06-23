<?php

namespace Tests\Concerns;

use Database\Seeders\NotificationTypeSettingSeeder;
use Database\Seeders\RoleSeeder;

trait SeedsRolesAndPermissions
{
    protected function seedRolesAndPermissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(NotificationTypeSettingSeeder::class);
    }
}
