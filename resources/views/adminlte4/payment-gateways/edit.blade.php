<x-adminlte4::layouts.app :title="__('web/payments.gateways.edit_title')">
    <x-adminlte4::card :title="__('web/payments.gateways.edit_title')">
        @include(config('web.layout').'.payment-gateways._form', [
            'action' => route('payment-gateways.update', $gateway->id),
            'method' => 'PUT',
            'gateway' => $gateway,
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
