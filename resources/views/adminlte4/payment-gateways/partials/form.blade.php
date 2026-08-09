<x-adminlte4::form.ajax :action="$action" :method="$method">
    <x-adminlte4::form.input
        name="name"
        :label="__('web/payments.gateways.form.name')"
        :value="$gateway->name ?? null"
        required
    />
    <x-adminlte4::form.input
        name="display_name"
        :label="__('web/payments.gateways.form.display_name')"
        :value="$gateway->display_name ?? null"
        required
    />
    <x-adminlte4::form.input
        name="priority"
        type="number"
        :label="__('web/payments.gateways.form.priority')"
        :value="$gateway->priority ?? 0"
        min="0"
    />
    <div class="form-check form-switch mb-3">
        <input type="hidden" name="is_active" value="0">
        <input
            class="form-check-input"
            type="checkbox"
            id="gateway-is-active"
            name="is_active"
            value="1"
            @checked($gateway->is_active ?? true)
        >
        <label class="form-check-label" for="gateway-is-active">{{ __('web/payments.gateways.form.is_active') }}</label>
    </div>
    <x-adminlte4::form.submit :label="__('ui.actions.save')" />
</x-adminlte4::form.ajax>
