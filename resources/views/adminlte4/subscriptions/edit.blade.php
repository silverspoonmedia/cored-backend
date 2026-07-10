<x-adminlte4::layouts.app :title="__('web/payments.subscriptions.edit_title')">
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/payments.subscriptions.edit_card_title', ['number' => $subscription['subscription_number'] ?? $subscription['id'] ?? ''])">
                @include(config('web.layout').'.subscriptions._form', [
                    'action' => route('subscriptions.update', $subscription['id']),
                    'method' => 'PUT',
                    'isEdit' => true,
                    'subscription' => $subscription,
                    'submitLabel' => __('ui.actions.save'),
                ])
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
