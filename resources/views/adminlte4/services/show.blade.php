<x-adminlte4::layouts.app :title="__('web/services.show_title')">
    @php
        \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('services.show', data_get($service, 'name'));
        $prices = collect(data_get($service, 'prices', []));
        $defaultCurrency = data_get($prices->first(), 'currency', 'USD');
    @endphp

    <x-adminlte4::card :title="data_get($service, 'name')">
        <dl class="row mb-3">
            <dt class="col-sm-3">{{ __('web/services.columns.code') }}</dt>
            <dd class="col-sm-9"><code>{{ data_get($service, 'code') }}</code></dd>
            <dt class="col-sm-3">{{ __('web/services.columns.status') }}</dt>
            <dd class="col-sm-9">{{ data_get($service, 'status') }}</dd>
            <dt class="col-sm-3">{{ __('web/services.columns.interval') }}</dt>
            <dd class="col-sm-9">{{ data_get($service, 'billing_interval') }}</dd>
            <dt class="col-sm-3">{{ __('web/services.form.plan_kind') }}</dt>
            <dd class="col-sm-9">{{ data_get($service, 'plan_kind') ?: '—' }}</dd>
            <dt class="col-sm-3">{{ __('web/services.form.storage_bytes') }}</dt>
            <dd class="col-sm-9">{{ data_get($service, 'storage_bytes_human') ?: data_get($service, 'storage_bytes', 0) }}</dd>
            <dt class="col-sm-3">{{ __('web/services.columns.prices') }}</dt>
            <dd class="col-sm-9">
                @forelse ($prices as $price)
                    <div>{{ data_get($price, 'amount_formatted') ?: (data_get($price, 'currency').' '.data_get($price, 'amount')) }}</div>
                @empty
                    —
                @endforelse
            </dd>
            <dt class="col-sm-3">{{ __('web/services.form.description') }}</dt>
            <dd class="col-sm-9">{{ data_get($service, 'description') ?: '—' }}</dd>
        </dl>

        @can('purchase service')
            @php($canInitiatePayment = true)
        @else
            @php($canInitiatePayment = \Illuminate\Support\Facades\Gate::allows('initiate payment'))
        @endcan

        @if (($canInitiatePayment) && data_get($service, 'status') === 'active' && $prices->isNotEmpty())
            <form action="{{ route('services.checkout', data_get($service, 'id')) }}" method="POST" class="d-flex gap-2 align-items-end">
                @csrf
                <div>
                    <label class="form-label" for="checkout-currency">{{ __('web/services.show.checkout_currency') }}</label>
                    <select name="currency" id="checkout-currency" class="form-select">
                        @foreach ($prices as $price)
                            <option value="{{ data_get($price, 'currency') }}" @selected(data_get($price, 'currency') === $defaultCurrency)>
                                {{ data_get($price, 'amount_formatted') ?: (data_get($price, 'currency').' — '.data_get($price, 'amount')) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-credit-card me-1" aria-hidden="true"></i>{{ __('web/services.show.start_checkout') }}
                </button>
            </form>
        @endif

        <div class="mt-3">
            <a href="{{ route('services.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('ui.actions.back') }}</a>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
