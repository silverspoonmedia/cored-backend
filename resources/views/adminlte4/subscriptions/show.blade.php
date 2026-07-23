<x-adminlte4::layouts.app :title="__('web/payments.subscriptions.show_title')">
    <x-adminlte4::card :title="__('web/payments.subscriptions.show_card_title', ['number' => $subscription['subscription_number'] ?? $subscription['id'] ?? ''])">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.subscriber_name') }}</dt>
            <dd class="col-sm-9">{{ $subscription['subscriber_name'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.subscriber_email') }}</dt>
            <dd class="col-sm-9">{{ $subscription['subscriber_email'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.subscriber_phone') }}</dt>
            <dd class="col-sm-9">{{ $subscription['subscriber_phone'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.plan_name') }}</dt>
            <dd class="col-sm-9">{{ $subscription['plan_name'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.plan_description') }}</dt>
            <dd class="col-sm-9">{{ $subscription['plan_description'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.billing_cycle') }}</dt>
            <dd class="col-sm-9">{{ $subscription['billing_cycle'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.amount') }}</dt>
            <dd class="col-sm-9">{{ $subscription['amount_formatted'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.status') }}</dt>
            <dd class="col-sm-9"><span class="badge text-bg-light border">{{ $subscription['status'] ?? '—' }}</span></dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.auto_renew') }}</dt>
            <dd class="col-sm-9">{{ ! empty($subscription['auto_renew']) ? 'Yes' : 'No' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.started_at') }}</dt>
            <dd class="col-sm-9">{{ $subscription['started_at_formatted'] ?? $subscription['started_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.expires_at') }}</dt>
            <dd class="col-sm-9">{{ $subscription['expires_at_formatted'] ?? $subscription['expires_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.trial_ends_at') }}</dt>
            <dd class="col-sm-9">{{ $subscription['trial_ends_at_formatted'] ?? $subscription['trial_ends_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/payments.subscriptions.fields.created_at') }}</dt>
            <dd class="col-sm-9">{{ $subscription['created_at_formatted'] ?? $subscription['created_at'] ?? '—' }}</dd>
        </dl>

        <x-slot:actions>
            <a href="{{ route('subscriptions.edit', $subscription['id']) }}" class="btn btn-primary btn-sm">
                {{ __('ui.datatable.edit') }}
            </a>
            <a href="{{ route('subscriptions.index') }}" class="btn btn-outline-secondary btn-sm">
                {{ __('web/payments.subscriptions.back_to_list') }}
            </a>
        </x-slot:actions>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
