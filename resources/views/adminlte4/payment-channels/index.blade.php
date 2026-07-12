@php
    $channels = is_array($channels ?? null) ? $channels : [];
    $totalChannels = count($channels);
    $activeChannels = collect($channels)->where('is_active', true)->count();
    $allRoutes = collect($channels)->flatMap(fn ($channel) => data_get($channel, 'routes', []));
    $totalRoutes = $allRoutes->count();
    $activeRoutes = $allRoutes->where('is_active', true)->count();

    $kindMeta = [
        'qris' => ['icon' => 'bi-qr-code', 'class' => 'pc-kind-qris', 'label' => __('web/payments.channels.kinds.qris')],
        'va' => ['icon' => 'bi-bank', 'class' => 'pc-kind-va', 'label' => __('web/payments.channels.kinds.va')],
        'ewallet' => ['icon' => 'bi-wallet2', 'class' => 'pc-kind-ewallet', 'label' => __('web/payments.channels.kinds.ewallet')],
    ];
@endphp

<x-adminlte4::layouts.app :title="__('web/payments.channels.title')">
    <div class="pc-admin">
        <x-adminlte4::card :title="__('web/payments.channels.list_title')">
            @if ($loadError)
                <div class="alert alert-danger mb-0">{{ $loadError }}</div>
            @else
                <div class="pc-admin__intro mb-4">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="pc-admin__intro-icon" aria-hidden="true">
                            <i class="bi bi-diagram-3"></i>
                        </span>
                        <div>
                            <h2 class="h5 mb-1">{{ __('web/payments.channels.intro.title') }}</h2>
                            <p class="text-muted mb-0">{{ __('web/payments.channels.intro.lead') }}</p>
                        </div>
                    </div>

                    <div class="pc-admin__steps">
                        @foreach (__('web/payments.channels.intro.steps') as $index => $step)
                            <div class="pc-admin__step">
                                <span class="pc-admin__step-num">{{ $index + 1 }}</span>
                                <div class="fw-semibold small">{{ $step['title'] }}</div>
                                <div class="text-muted small mb-0">{{ $step['body'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="pc-admin__stats mb-4">
                    <div class="pc-admin__stat">
                        <div class="text-muted small">{{ __('web/payments.channels.stats.channels_active') }}</div>
                        <div class="pc-admin__stat-value">{{ $activeChannels }}<span class="fs-6 text-muted fw-normal"> / {{ $totalChannels }}</span></div>
                    </div>
                    <div class="pc-admin__stat">
                        <div class="text-muted small">{{ __('web/payments.channels.stats.routes_active') }}</div>
                        <div class="pc-admin__stat-value">{{ $activeRoutes }}<span class="fs-6 text-muted fw-normal"> / {{ $totalRoutes }}</span></div>
                    </div>
                    <div class="pc-admin__stat">
                        <div class="text-muted small">{{ __('web/payments.channels.stats.checkout_hint') }}</div>
                        <div class="small fw-semibold mb-0 text-body">{{ __('web/payments.channels.stats.checkout_body') }}</div>
                    </div>
                </div>

                <div
                    data-payment-channels-admin
                    data-update-url-template="{{ url('payment-channels') }}/__CODE__"
                    data-route-update-url-template="{{ url('payment-channel-routes') }}/__ROUTE__"
                    data-messages="{{ json_encode([
                        'updated' => __('web/payments.channels.updated'),
                        'routeUpdated' => __('web/payments.channels.route_updated'),
                        'failed' => __('web/payments.checkout.sync_failed'),
                        'searchPlaceholder' => __('web/payments.channels.search_placeholder'),
                        'noResults' => __('web/payments.channels.no_results'),
                        'primaryRoute' => __('web/payments.channels.primary_route'),
                        'inactiveChannel' => __('web/payments.channels.inactive_channel'),
                        'priorityHint' => __('web/payments.channels.priority_hint'),
                        'visibleCountTemplate' => trans_choice('web/payments.channels.visible_count', $totalChannels, ['count' => ':count']),
                    ]) }}"
                >
                    <div class="pc-admin__toolbar mb-3">
                        <div class="pc-admin__search flex-grow-1">
                            <label class="form-label small text-muted mb-1" for="pc-channel-search">{{ __('web/payments.channels.search_label') }}</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input
                                    id="pc-channel-search"
                                    type="search"
                                    class="form-control"
                                    placeholder="{{ __('web/payments.channels.search_placeholder') }}"
                                    data-payment-channel-search
                                    @disabled($totalChannels === 0)
                                >
                            </div>
                        </div>
                        <div class="text-muted small align-self-end" data-payment-channel-count>
                            {{ trans_choice('web/payments.channels.visible_count', $totalChannels, ['count' => $totalChannels]) }}
                        </div>
                    </div>

                    @if ($totalChannels === 0)
                        <div class="pc-admin__empty">
                            <i class="bi bi-credit-card-2-front fs-2 d-block mb-2"></i>
                            <p class="mb-0">{{ __('web/payments.channels.empty') }}</p>
                        </div>
                    @else
                        <div class="d-grid gap-3" data-payment-channel-list>
                            @foreach ($channels as $channel)
                                @php
                                    $code = (string) data_get($channel, 'code');
                                    $kind = (string) data_get($channel, 'kind', 'qris');
                                    $meta = $kindMeta[$kind] ?? ['icon' => 'bi-credit-card', 'class' => '', 'label' => $kind];
                                    $isActive = (bool) data_get($channel, 'is_active');
                                    $routes = collect(data_get($channel, 'routes', []))
                                        ->sortBy('priority')
                                        ->values()
                                        ->all();
                                    $primaryRoute = collect($routes)->first(fn ($route) => (bool) data_get($route, 'is_active'));
                                    $primaryRouteId = $primaryRoute ? (string) data_get($primaryRoute, 'id') : null;
                                @endphp

                                <article
                                    class="pc-channel-card {{ $meta['class'] }} {{ $isActive ? '' : 'is-inactive' }}"
                                    data-payment-channel-card
                                    data-channel-code="{{ $code }}"
                                    data-channel-name="{{ strtolower(data_get($channel, 'display_name', $code)) }}"
                                    data-channel-kind="{{ $kind }}"
                                >
                                    <header class="pc-channel-card__head">
                                        <span class="pc-channel-card__icon" aria-hidden="true">
                                            <i class="bi {{ $meta['icon'] }}"></i>
                                        </span>

                                        <div class="flex-grow-1 min-w-0">
                                            <h3 class="pc-channel-card__title">{{ data_get($channel, 'display_name') }}</h3>
                                            <div class="pc-channel-card__meta">
                                                <span class="badge text-bg-light border"><code>{{ $code }}</code></span>
                                                <span class="badge text-bg-light border">{{ $meta['label'] }}</span>
                                                <span class="badge text-bg-light border">
                                                    {{ __('web/payments.channels.columns.sort_order') }}: {{ data_get($channel, 'sort_order') }}
                                                </span>
                                                @if (! $isActive)
                                                    <span class="badge text-bg-secondary" data-channel-inactive-badge>{{ __('web/payments.channels.inactive_channel') }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="pc-channel-card__actions">
                                            @can('manage payment gateway')
                                                <div class="form-check form-switch m-0">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        role="switch"
                                                        id="channel-toggle-{{ $code }}"
                                                        data-payment-channel-toggle="{{ $code }}"
                                                        @checked($isActive)
                                                    >
                                                    <label class="form-check-label small" for="channel-toggle-{{ $code }}">
                                                        {{ __('web/payments.channels.columns.is_active') }}
                                                    </label>
                                                </div>
                                            @else
                                                <span class="badge text-bg-{{ $isActive ? 'success' : 'secondary' }}">
                                                    {{ $isActive ? __('web/payments.channels.status.active') : __('web/payments.channels.status.inactive') }}
                                                </span>
                                            @endcan
                                        </div>
                                    </header>

                                    <div class="pc-channel-card__body">
                                        <div class="pc-routes-heading">
                                            <div>
                                                <div class="fw-semibold">{{ __('web/payments.channels.routes_title') }}</div>
                                                <div class="text-muted small">{{ __('web/payments.channels.routes_lead') }}</div>
                                            </div>
                                            <span class="badge text-bg-light border">
                                                {{ trans_choice('web/payments.channels.route_count', count($routes), ['count' => count($routes)]) }}
                                            </span>
                                        </div>

                                        @if ($routes === [])
                                            <p class="text-muted small mb-0">{{ __('web/payments.channels.no_routes') }}</p>
                                        @else
                                            <div class="pc-route-list">
                                                @foreach ($routes as $index => $route)
                                                    @php
                                                        $routeId = (string) data_get($route, 'id');
                                                        $routeActive = (bool) data_get($route, 'is_active');
                                                        $gatewayName = (string) data_get($route, 'gateway.name', '');
                                                        $gatewayClass = $gatewayName === 'midtrans'
                                                            ? 'pc-gateway-midtrans'
                                                            : ($gatewayName === 'ipaymu' ? 'pc-gateway-ipaymu' : '');
                                                        $isPrimary = $routeActive && $routeId === (string) $primaryRouteId;
                                                    @endphp

                                                    <div
                                                        class="pc-route-item {{ $routeActive ? '' : 'is-inactive' }} {{ $isPrimary ? 'is-primary' : '' }}"
                                                        data-payment-route-item
                                                        data-route-id="{{ $routeId }}"
                                                    >
                                                        <span class="pc-route-rank" title="{{ __('web/payments.channels.priority_hint') }}">
                                                            {{ $index + 1 }}
                                                        </span>

                                                        <div class="min-w-0">
                                                            <div class="pc-route-gateway {{ $gatewayClass }}">
                                                                {{ data_get($route, 'gateway.display_name', data_get($route, 'gateway.name', '—')) }}
                                                                @if ($isPrimary)
                                                                    <span class="badge text-bg-success ms-1" data-route-primary-badge>{{ __('web/payments.channels.primary_route') }}</span>
                                                                @endif
                                                            </div>
                                                            <div class="pc-route-meta">
                                                                <span>{{ __('web/payments.channels.route_columns.provider_type') }}:</span>
                                                                <code>{{ data_get($route, 'provider_payment_type') }}</code>
                                                                <span class="mx-1">·</span>
                                                                <span>{{ __('web/payments.channels.route_columns.provider_channel') }}:</span>
                                                                <span>{{ data_get($route, 'provider_payment_channel') ?: '—' }}</span>
                                                            </div>
                                                        </div>

                                                        <div class="pc-route-controls">
                                                            @can('manage payment gateway')
                                                                <div>
                                                                    <label class="form-label small text-muted mb-0" for="route-priority-{{ $routeId }}">
                                                                        {{ __('web/payments.channels.route_columns.priority') }}
                                                                    </label>
                                                                    <input
                                                                        id="route-priority-{{ $routeId }}"
                                                                        type="number"
                                                                        class="form-control form-control-sm text-center pc-route-priority"
                                                                        min="0"
                                                                        max="9999"
                                                                        value="{{ data_get($route, 'priority') }}"
                                                                        data-payment-route-priority="{{ $routeId }}"
                                                                        title="{{ __('web/payments.channels.priority_hint') }}"
                                                                    >
                                                                </div>
                                                                <div class="form-check form-switch m-0">
                                                                    <input
                                                                        class="form-check-input"
                                                                        type="checkbox"
                                                                        role="switch"
                                                                        id="route-toggle-{{ $routeId }}"
                                                                        data-payment-route-toggle="{{ $routeId }}"
                                                                        @checked($routeActive)
                                                                    >
                                                                    <label class="form-check-label small" for="route-toggle-{{ $routeId }}">
                                                                        {{ __('web/payments.channels.route_columns.is_active') }}
                                                                    </label>
                                                                </div>
                                                            @else
                                                                <span class="badge text-bg-{{ $routeActive ? 'success' : 'secondary' }}">
                                                                    {{ $routeActive ? __('web/payments.channels.status.active') : __('web/payments.channels.status.inactive') }}
                                                                </span>
                                                                <span class="text-muted small">{{ data_get($route, 'priority') }}</span>
                                                            @endcan
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <p class="text-muted small mt-3 mb-0 d-none" data-payment-channel-no-results>
                            {{ __('web/payments.channels.no_results') }}
                        </p>
                    @endif

                    <div class="pc-admin-toast" data-payment-admin-toast role="status" aria-live="polite"></div>
                </div>
            @endif
        </x-adminlte4::card>
    </div>

    @vite(['resources/css/payments/channels-admin.css', 'resources/js/payments/channels-admin.js'])
</x-adminlte4::layouts.app>
