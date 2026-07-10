<x-adminlte4::layouts.app :title="__('web/payments.subscriptions.create_title')">
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte4::card :title="__('web/payments.subscriptions.new_card_title')">
                @include(config('web.layout').'.subscriptions._form', [
                    'action' => route('subscriptions.store'),
                    'method' => 'POST',
                    'isEdit' => false,
                    'subscription' => null,
                    'submitLabel' => __('ui.actions.save'),
                ])
            </x-adminlte4::card>
        </div>
    </div>
</x-adminlte4::layouts.app>
