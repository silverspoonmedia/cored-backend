<x-adminlte4::layouts.app :title="__('web/payments.gateways.create_title')">
    <x-adminlte4::card :title="__('web/payments.gateways.create_title')">
        @include(config('web.layout').'.payment-gateways._form', [
            'action' => route('payment-gateways.store'),
            'method' => 'POST',
            'gateway' => null,
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
