<?php

namespace Tests\Concerns;

use App\Domain\Identity\Database\Seeders\RoleSeeder;
use App\Domain\Notification\Database\Seeders\NotificationTypeSettingSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * The host's version of the kernel trait: the core role ladder **plus** the
 * notification type settings.
 *
 * The kernel trait (`App\Support\Testing\Concerns\SeedsRolesAndPermissions`) seeds
 * the ladder only — the kernel may not name a sibling package. The host is
 * entitled to both, so this is the one place the pair is assembled.
 */
trait SeedsRolesAndPermissions
{
    protected function seedRolesAndPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(RoleSeeder::class);
        $this->seed(NotificationTypeSettingSeeder::class);
    }
}
