<?php

/**
 * Domain module registry (logical monorepo under app/Domain/*).
 *
 * core => true  — platform boilerplate (Identity, Navigation, …). Informational
 *                 for docs/list; domain:remove uses a hardcoded PLATFORM_CORE
 *                 list in DomainManager — flipping this flag cannot unlock remove.
 *
 * locked => true  — product domain; domain:remove refuses until you manually
 *                   set locked => false in this file (no artisan unlock command).
 * locked => false — removable (Playground demo, or domain:make --removable).
 *
 * Provider classes still register in bootstrap/providers.php; Artisan domain:*
 * commands keep this file and providers/routes in sync.
 */
return [
    'Identity' => ['core' => true],
    'Navigation' => ['core' => true],
    'Notification' => ['core' => true],
    'ActivityLog' => ['core' => true],
    'PaymentGateway' => ['core' => true],
    'Service' => ['core' => true],
    'Playground' => ['core' => false, 'locked' => true],
    'Product' => ['core' => false, 'locked' => true],
    'FileHosting' => ['core' => false, 'locked' => true],
];