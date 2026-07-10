@php
    $billingCycles = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
    $statuses = ['trial', 'active', 'expired', 'cancelled', 'suspended'];
@endphp

<x-adminlte4::form.ajax :action="$action" :method="$method">
    <x-adminlte4::form.input
        name="subscriber_name"
        :label="__('web/payments.subscriptions.fields.subscriber_name')"
        :value="old('subscriber_name', $subscription['subscriber_name'] ?? null)"
        required
    />
    <x-adminlte4::form.input
        name="subscriber_email"
        type="email"
        :label="__('web/payments.subscriptions.fields.subscriber_email')"
        :value="old('subscriber_email', $subscription['subscriber_email'] ?? null)"
        required
    />
    <x-adminlte4::form.input
        name="subscriber_phone"
        :label="__('web/payments.subscriptions.fields.subscriber_phone')"
        :value="old('subscriber_phone', $subscription['subscriber_phone'] ?? null)"
    />
    <x-adminlte4::form.input
        name="plan_name"
        :label="__('web/payments.subscriptions.fields.plan_name')"
        :value="old('plan_name', $subscription['plan_name'] ?? null)"
        required
    />
    <div class="mb-3">
        <label for="plan_description" class="form-label">{{ __('web/payments.subscriptions.fields.plan_description') }}</label>
        <textarea name="plan_description" id="plan_description" class="form-control" rows="3">{{ old('plan_description', $subscription['plan_description'] ?? null) }}</textarea>
        <x-adminlte4::form.error name="plan_description" />
    </div>
    <x-adminlte4::form.select
        name="billing_cycle"
        :label="__('web/payments.subscriptions.fields.billing_cycle')"
        :options="array_combine($billingCycles, $billingCycles)"
        :selected="old('billing_cycle', $subscription['billing_cycle'] ?? 'monthly')"
        required
    />
    <x-adminlte4::form.input
        name="amount"
        type="number"
        step="0.01"
        min="0"
        :label="__('web/payments.subscriptions.fields.amount')"
        :value="old('amount', $subscription['amount'] ?? null)"
        required
    />
    <x-adminlte4::form.input
        name="currency"
        :label="__('web/payments.subscriptions.fields.currency')"
        :value="old('currency', $subscription['currency'] ?? 'IDR')"
        maxlength="3"
    />

    @if ($isEdit)
        <x-adminlte4::form.select
            name="status"
            :label="__('web/payments.subscriptions.fields.status')"
            :options="array_combine($statuses, $statuses)"
            :selected="old('status', $subscription['status'] ?? 'active')"
        />
    @endif

    <x-adminlte4::form.checkbox
        name="auto_renew"
        :label="__('web/payments.subscriptions.fields.auto_renew')"
        :checked="old('auto_renew', $subscription['auto_renew'] ?? false)"
        value="1"
    />

    <div class="d-flex gap-2">
        <x-adminlte4::form.submit :label="$submitLabel" class="btn btn-primary" />
        <a href="{{ route('subscriptions.index') }}" class="btn btn-outline-secondary">{{ __('ui.actions.cancel') }}</a>
    </div>
</x-adminlte4::form.ajax>
