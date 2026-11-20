<?php

namespace Database\Seeders;

use App\Domain\Identity\Database\Seeders\RoleSeeder as KernelRoleSeeder;

/**
 * @deprecated The seeder moved into the kernel package — use
 *             {@see KernelRoleSeeder} (`App\Domain\Identity\Database\Seeders\RoleSeeder`).
 *             This shim keeps the host's `DatabaseSeeder` and any host fixture that
 *             still names `Database\Seeders\RoleSeeder` working.
 */
class RoleSeeder extends KernelRoleSeeder {}
