<x-adminlte4::layouts.app :title="__('web/payments.channels.title')">
    <x-adminlte4::card :title="__('web/payments.channels.list_title')">
        @if ($loadError)
            <div class="alert alert-danger mb-0">{{ $loadError }}</div>
        @else
            <div
                data-payment-channels-admin
                data-update-url-template="{{ url('payment-channels') }}/__CODE__"
                data-route-update-url-template="{{ url('payment-channel-routes') }}/__ROUTE__"
                data-messages="{{ json_encode([
                    'updated' => __('web/payments.channels.updated'),
                    'routeUpdated' => __('web/payments.channels.route_updated'),
                    'failed' => __('web/payments.checkout.sync_failed'),
                ]) }}"
            >
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/payments.channels.columns.code') }}</th>
                                <th>{{ __('web/payments.channels.columns.display_name') }}</th>
                                <th>{{ __('web/payments.channels.columns.kind') }}</th>
                                <th class="text-center">{{ __('web/payments.channels.columns.sort_order') }}</th>
                                <th class="text-center">{{ __('web/payments.channels.columns.is_active') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($channels as $channel)
                                <tr>
                                    <td><code>{{ data_get($channel, 'code') }}</code></td>
                                    <td>{{ data_get($channel, 'display_name') }}</td>
                                    <td>{{ data_get($channel, 'kind') }}</td>
                                    <td class="text-center">{{ data_get($channel, 'sort_order') }}</td>
                                    <td class="text-center">
                                        @can('manage payment gateway')
                                            <div class="form-check form-switch d-inline-block">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    data-payment-channel-toggle="{{ data_get($channel, 'code') }}"
                                                    @checked(data_get($channel, 'is_active'))
                                                >
                                            </div>
                                        @else
                                            <span class="badge text-bg-{{ data_get($channel, 'is_active') ? 'success' : 'secondary' }}">
                                                {{ data_get($channel, 'is_active') ? 'Yes' : 'No' }}
                                            </span>
                                        @endcan
                                    </td>
                                </tr>
                                @if (! empty(data_get($channel, 'routes')))
                                    <tr>
                                        <td colspan="5" class="bg-light">
                                            <div class="small fw-semibold mb-2">{{ __('web/payments.channels.routes_title') }}</div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0 bg-white">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('web/payments.channels.route_columns.gateway') }}</th>
                                                            <th>{{ __('web/payments.channels.route_columns.provider_type') }}</th>
                                                            <th>{{ __('web/payments.channels.route_columns.provider_channel') }}</th>
                                                            <th class="text-center">{{ __('web/payments.channels.route_columns.priority') }}</th>
                                                            <th class="text-center">{{ __('web/payments.channels.route_columns.is_active') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach (data_get($channel, 'routes', []) as $route)
                                                            <tr>
                                                                <td>{{ data_get($route, 'gateway.display_name', data_get($route, 'gateway.name')) }}</td>
                                                                <td><code>{{ data_get($route, 'provider_payment_type') }}</code></td>
                                                                <td>{{ data_get($route, 'provider_payment_channel') ?: '—' }}</td>
                                                                <td class="text-center" style="width: 6rem;">
                                                                    @can('manage payment gateway')
                                                                        <input
                                                                            type="number"
                                                                            class="form-control form-control-sm text-center"
                                                                            min="0"
                                                                            max="9999"
                                                                            value="{{ data_get($route, 'priority') }}"
                                                                            data-payment-route-priority="{{ data_get($route, 'id') }}"
                                                                        >
                                                                    @else
                                                                        {{ data_get($route, 'priority') }}
                                                                    @endcan
                                                                </td>
                                                                <td class="text-center">
                                                                    @can('manage payment gateway')
                                                                        <div class="form-check form-switch d-inline-block">
                                                                            <input
                                                                                class="form-check-input"
                                                                                type="checkbox"
                                                                                role="switch"
                                                                                data-payment-route-toggle="{{ data_get($route, 'id') }}"
                                                                                @checked(data_get($route, 'is_active'))
                                                                            >
                                                                        </div>
                                                                    @else
                                                                        <span class="badge text-bg-{{ data_get($route, 'is_active') ? 'success' : 'secondary' }}">
                                                                            {{ data_get($route, 'is_active') ? 'Yes' : 'No' }}
                                                                        </span>
                                                                    @endcan
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">—</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-adminlte4::card>

    @vite('resources/js/payments/channels-admin.js')
</x-adminlte4::layouts.app>
