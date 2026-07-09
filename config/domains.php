<?php

/**
 * Domain module registry (logical monorepo under app/Domain/*).
 *
 * core => true  — boilerplate; domain:remove refuses (Identity + platform modules).
 * core => false — removable (Playground demo + anything created via domain:make).
 *
 * Provider classes still register in bootstrap/providers.php; Artisan domain:*
 * commands keep this file and providers/routes in sync.
 */
return [
    'Identity' => ['core' => true],
    'Navigation' => ['core' => true],
    'Notification' => ['core' => true],
    'ActivityLog' => ['core' => true],
    'Approval' => ['core' => true],
    'Playground' => ['core' => false],
];
