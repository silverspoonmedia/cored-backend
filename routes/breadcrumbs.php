<?php

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as Trail;

/*
|--------------------------------------------------------------------------
| Breadcrumb trails (diglactic/laravel-breadcrumbs)
|--------------------------------------------------------------------------
| Single source of truth for every authenticated web screen's breadcrumb.
| Trails are keyed by ROUTE NAME, so the layout can auto-render the trail for
| the current route (see resources/views/adminlte4/components/layouts/app.blade.php).
|
| All titles go through __() against the same lang keys the views already use
| (lang/{en,id,ja}/menu.php, ui.php, web/*.php) — no hardcoded copy, so every
| locale stays covered. Parent links reuse route() exactly as before.
*/

// Dashboard — root of every authenticated trail.
Breadcrumbs::for('dashboard', function (Trail $trail): void {
    $trail->push(__('menu.dashboard'), route('dashboard'));
});

// Users.
Breadcrumbs::for('users.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/users.breadcrumb'), route('users.index'));
});

Breadcrumbs::for('users.create', function (Trail $trail): void {
    $trail->parent('users.index');
    $trail->push(__('ui.breadcrumb.add'));
});

Breadcrumbs::for('users.edit', function (Trail $trail): void {
    $trail->parent('users.index');
    $trail->push(__('ui.breadcrumb.edit'));
});

// Services (sellable catalog + purchase checkout).
Breadcrumbs::for('services.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/services.breadcrumb'), route('services.index'));
});

Breadcrumbs::for('services.create', function (Trail $trail): void {
    $trail->parent('services.index');
    $trail->push(__('ui.breadcrumb.add'));
});

Breadcrumbs::for('services.edit', function (Trail $trail): void {
    $trail->parent('services.index');
    $trail->push(__('ui.breadcrumb.edit'));
});

Breadcrumbs::for('services.show', function (Trail $trail, string $serviceName): void {
    $trail->parent('services.index');
    $trail->push($serviceName);
});

Breadcrumbs::for('service-purchases.show', function (Trail $trail, string $purchaseId): void {
    $trail->parent('services.index');
    $trail->push(__('web/services.purchase_title'));
});

// Navigation menus.
Breadcrumbs::for('menus.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/menus.breadcrumb'), route('menus.index'));
});

Breadcrumbs::for('menus.create', function (Trail $trail): void {
    $trail->parent('menus.index');
    $trail->push(__('ui.breadcrumb.add'));
});

Breadcrumbs::for('menus.edit', function (Trail $trail): void {
    $trail->parent('menus.index');
    $trail->push(__('ui.breadcrumb.edit'));
});

Breadcrumbs::for('menus.trashed', function (Trail $trail): void {
    $trail->parent('menus.index');
    $trail->push(__('web/menus.trashed_breadcrumb'));
});

// Activity audit log.
Breadcrumbs::for('activity-logs.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/activity-log.breadcrumb'));
});

Breadcrumbs::for('activity-logs.show', function (Trail $trail): void {
    $trail->parent('activity-logs.index');
    $trail->push(__('web/activity-log.show_breadcrumb'));
});

// Notifications.
Breadcrumbs::for('notifications.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/notifications.breadcrumb'));
});

// Self-service profile.
Breadcrumbs::for('profile.password.edit', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/profile.password_title'));
});
