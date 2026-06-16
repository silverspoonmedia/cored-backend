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

// Orders (playground master-detail).
Breadcrumbs::for('orders.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/orders.breadcrumb'), route('orders.index'));
});

Breadcrumbs::for('orders.create', function (Trail $trail): void {
    $trail->parent('orders.index');
    $trail->push(__('ui.breadcrumb.add'));
});

Breadcrumbs::for('orders.edit', function (Trail $trail): void {
    $trail->parent('orders.index');
    $trail->push(__('ui.breadcrumb.edit'));
});

// Order detail — order number is dynamic, passed in by the view (no DB lookup
// in the web layer, which is a pure API consumer).
Breadcrumbs::for('orders.show', function (Trail $trail, string $orderNumber): void {
    $trail->parent('orders.index');
    $trail->push($orderNumber);
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
