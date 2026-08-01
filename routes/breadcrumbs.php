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
	$trail->push(__('web/activity-log.breadcrumb'), route('activity-logs.index'));
});

Breadcrumbs::for('activity-logs.show', function (Trail $trail): void {
    $trail->parent('activity-logs.index');
    $trail->push(__('web/activity-log.show_breadcrumb'));
});

// Notifications.
Breadcrumbs::for('notifications.index', function (Trail $trail): void {
	$trail->parent('dashboard');
	$trail->push(__('web/notifications.breadcrumb'), route('notifications.index'));
});

// Self-service profile.
Breadcrumbs::for('profile.password.edit', function (Trail $trail): void {
	$trail->parent('dashboard');
	$trail->push(__('web/profile.password_title'), route('profile.password.edit'));
});

// Payment gateway — invoices.
Breadcrumbs::for('invoices.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/payments.invoices.title'), route('invoices.index'));
});

Breadcrumbs::for('invoices.show', function (Trail $trail, string $invoiceNumber): void {
    $trail->parent('invoices.index');
    $trail->push(__('web/payments.invoices.show_title'));
});

// Payment income dashboard.
Breadcrumbs::for('payments.income.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/payments.income.title'), route('payments.income.index'));
});

// Payment channels admin.
Breadcrumbs::for('payment-channels.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/payments.channels.title'), route('payment-channels.index'));
});

// Payment gateways admin.
Breadcrumbs::for('payment-gateways.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/payments.gateways.title'), route('payment-gateways.index'));
});

// Subscriptions.
Breadcrumbs::for('subscriptions.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/payments.subscriptions.title'), route('subscriptions.index'));
});

Breadcrumbs::for('subscriptions.create', function (Trail $trail): void {
    $trail->parent('subscriptions.index');
    $trail->push(__('ui.breadcrumb.add'));
});

Breadcrumbs::for('subscriptions.edit', function (Trail $trail): void {
    $trail->parent('subscriptions.index');
    $trail->push(__('ui.breadcrumb.edit'));
});

Breadcrumbs::for('subscriptions.show', function (Trail $trail, string $subscriptionId): void {
    $trail->parent('subscriptions.index');
    $trail->push(__('web/payments.subscriptions.show_title'));
});

// Inference admin.
Breadcrumbs::for('inference.model-cards.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/inference.model_cards.title'), route('inference.model-cards.index'));
});

Breadcrumbs::for('inference.model-cards.create', function (Trail $trail): void {
    $trail->parent('inference.model-cards.index');
    $trail->push(__('ui.breadcrumb.add'));
});

Breadcrumbs::for('inference.model-cards.edit', function (Trail $trail, string $modelCard): void {
    $trail->parent('inference.model-cards.index');
    $trail->push(__('ui.breadcrumb.edit'));
});

Breadcrumbs::for('inference.model-cards.show', function (Trail $trail, string $modelCard): void {
    $trail->parent('inference.model-cards.index');
    $trail->push(__('web/inference.model_cards.show_title'));
});

Breadcrumbs::for('inference.providers.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/inference.providers.title'), route('inference.providers.index'));
});

Breadcrumbs::for('inference.providers.create', function (Trail $trail): void {
    $trail->parent('inference.providers.index');
    $trail->push(__('ui.breadcrumb.add'));
});

Breadcrumbs::for('inference.providers.edit', function (Trail $trail, string $provider): void {
    $trail->parent('inference.providers.index');
    $trail->push(__('ui.breadcrumb.edit'));
});

Breadcrumbs::for('inference.providers.show', function (Trail $trail, string $provider): void {
    $trail->parent('inference.providers.index');
    $trail->push(__('web/inference.providers.show_title'));
});

Breadcrumbs::for('inference.usage-events.index', function (Trail $trail): void {
    $trail->parent('dashboard');
    $trail->push(__('web/inference.usage.title'), route('inference.usage-events.index'));
});
