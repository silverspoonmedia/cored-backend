<?php

// Core domains (config/domains.php kind=core). Order matches PLATFORM_CORE + Service.
require __DIR__.'/v1/identity.php';
require __DIR__.'/v1/navigation.php';
require __DIR__.'/v1/notification.php';
require __DIR__.'/v1/activity-log.php';
require __DIR__.'/v1/payment-gateway.php';
require __DIR__.'/v1/service.php';

// Product domains (kind=product). domain:make appends here; keep product requires last.
require __DIR__.'/v1/file-hosting.php';
