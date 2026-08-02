<x-adminlte4::layouts.app :title="__('web/currencies.title')">
    <x-adminlte4::card :title="__('web/currencies.title')" :subtitle="__('web/currencies.subtitle')">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/currencies.columns.code') }}</th>
                        <th>{{ __('web/currencies.columns.name') }}</th>
                        <th>{{ __('web/currencies.columns.symbol') }}</th>
                        <th>{{ __('web/currencies.columns.active') }}</th>
                        <th>{{ __('web/currencies.columns.settlement') }}</th>
                        @can('manage settings')
                            <th></th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($currencies as $currency)
                        <tr>
                            <td><code>{{ $currency['code'] }}</code></td>
                            <td>{{ $currency['name'] }}</td>
                            <td>{{ $currency['symbol'] }}</td>
                            <td>
                                <span class="badge text-bg-{{ ($currency['is_active'] ?? false) ? 'success' : 'secondary' }}">
                                    {{ ($currency['is_active'] ?? false) ? __('ui.yes') : __('ui.no') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge text-bg-{{ ($currency['is_settlement'] ?? false) ? 'primary' : 'secondary' }}">
                                    {{ ($currency['is_settlement'] ?? false) ? __('ui.yes') : __('ui.no') }}
                                </span>
                            </td>
                            @can('manage settings')
                                <td class="text-end">
                                    <form method="POST" action="{{ route('currencies.update', $currency['code']) }}" class="d-inline">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ ($currency['is_active'] ?? false) ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            {{ ($currency['is_active'] ?? false) ? __('web/currencies.deactivate') : __('web/currencies.activate') }}
                                        </button>
                                    </form>
                                    @if (! ($currency['is_settlement'] ?? false))
                                        <form method="POST" action="{{ route('currencies.update', $currency['code']) }}" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="is_settlement" value="1">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                {{ __('web/currencies.set_settlement') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
