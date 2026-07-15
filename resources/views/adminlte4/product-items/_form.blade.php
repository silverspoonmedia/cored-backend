{{--
    Shared product item create/edit form.
    Expected: $action, $method, $isEdit, $item|null, $submitLabel
--}}
@php
    $statusOptions = [
        'draft' => __('web/product-items.form.status_draft'),
        'active' => __('web/product-items.form.status_active'),
        'archived' => __('web/product-items.form.status_archived'),
    ];
@endphp

<x-adminlte4::form.ajax :action="$action" :method="$method">
    <x-adminlte4::form.input
        name="sku"
        :label="__('web/product-items.form.sku')"
        :value="$item->sku ?? null"
        required
    />
    <x-adminlte4::form.input
        name="name"
        :label="__('web/product-items.form.name')"
        :value="$item->name ?? null"
        required
    />
    <x-adminlte4::form.input
        name="description"
        :label="__('web/product-items.form.description')"
        :value="$item->description ?? null"
    />
    <x-adminlte4::form.input
        name="unit_price_cents"
        type="number"
        :label="__('web/product-items.form.unit_price_cents')"
        :value="$item->unit_price_cents ?? 0"
        required
    />
    <x-adminlte4::form.select
        name="status"
        :label="__('web/product-items.form.status')"
        :options="$statusOptions"
        :selected="$item->status ?? 'draft'"
        required
    />

    <div class="d-flex gap-2">
        <x-adminlte4::form.submit :label="$submitLabel" class="btn btn-primary" />
        <a href="{{ route('product-items.index') }}" class="btn btn-outline-secondary">{{ __('ui.actions.cancel') }}</a>
    </div>
</x-adminlte4::form.ajax>
