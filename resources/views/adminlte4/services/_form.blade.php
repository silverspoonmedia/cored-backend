{{--
    Shared service create/edit form.
    Expected: $action, $method, $isEdit, $service|null, $submitLabel
--}}
@php
    $statusOptions = [
        'draft' => __('web/services.form.status_draft'),
        'active' => __('web/services.form.status_active'),
        'archived' => __('web/services.form.status_archived'),
    ];
    $intervalOptions = [
        'monthly' => __('web/services.form.interval_monthly'),
        'quarterly' => __('web/services.form.interval_quarterly'),
        'yearly' => __('web/services.form.interval_yearly'),
        'once' => __('web/services.form.interval_once'),
    ];
    $existingPrices = is_object($service ?? null) && isset($service->prices)
        ? (array) $service->prices
        : (is_array($service->prices ?? null) ? $service->prices : [['currency' => 'USD', 'amount' => '10']]);
    if ($existingPrices === []) {
        $existingPrices = [['currency' => 'USD', 'amount' => '10']];
    }
@endphp

<x-adminlte4::form.ajax :action="$action" :method="$method">
    <x-adminlte4::form.input
        name="code"
        :label="__('web/services.form.code')"
        :value="$service->code ?? null"
        required
    />
    <x-adminlte4::form.input
        name="name"
        :label="__('web/services.form.name')"
        :value="$service->name ?? null"
        required
    />
    <x-adminlte4::form.input
        name="description"
        :label="__('web/services.form.description')"
        :value="$service->description ?? null"
    />
    <x-adminlte4::form.select
        name="billing_interval"
        :label="__('web/services.form.billing_interval')"
        :options="$intervalOptions"
        :selected="$service->billing_interval ?? 'monthly'"
        required
    />
    <x-adminlte4::form.input
        name="fulfillment_target"
        :label="__('web/services.form.fulfillment_target')"
        :value="$service->fulfillment_target ?? null"
    />
    <x-adminlte4::form.input
        name="entitlement"
        :label="__('web/services.form.entitlement')"
        :value="$service->entitlement ?? null"
    />
    <x-adminlte4::form.select
        name="status"
        :label="__('web/services.form.status')"
        :options="$statusOptions"
        :selected="$service->status ?? 'draft'"
        required
    />

    <div class="mb-3">
        <label class="form-label">{{ __('web/services.form.prices') }}</label>
        @foreach ($existingPrices as $i => $price)
            @php
                $row = is_array($price) ? $price : (array) $price;
            @endphp
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <input type="text" name="prices[{{ $i }}][currency]" class="form-control"
                        value="{{ $row['currency'] ?? 'USD' }}" maxlength="3"
                        placeholder="{{ __('web/services.form.price_currency') }}" required>
                </div>
                <div class="col-md-8">
                    <input type="number" step="0.01" min="0" name="prices[{{ $i }}][amount]" class="form-control"
                        value="{{ $row['amount'] ?? '' }}"
                        placeholder="{{ __('web/services.form.price_amount') }}" required>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex gap-2">
        <x-adminlte4::form.submit :label="$submitLabel" class="btn btn-primary" />
        <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">{{ __('ui.actions.cancel') }}</a>
    </div>
</x-adminlte4::form.ajax>
