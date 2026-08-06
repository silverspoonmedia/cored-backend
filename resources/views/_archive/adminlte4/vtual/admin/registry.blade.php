<x-adminlte4::layouts.app :title="__('web/vtual.claims.staff_registry')">
    <div class="vtual-admin">
        <x-adminlte4::card :title="__('web/vtual.claims.staff_registry')">
            <p class="text-muted small mb-3">{{ __('web/vtual.claims.staff_subtitle') }}</p>
            @include('adminlte4.vtual.partials.nav-admin')

            @include('adminlte4.vtual.admin.registry._stats')
            @include('adminlte4.vtual.admin.registry._orphan-form')
            @include('adminlte4.vtual.admin.registry._pending-table')
        </x-adminlte4::card>
    </div>

    @include('adminlte4.vtual.partials._confirm-modal')

    @vite('resources/css/vtual/admin.css')

    <x-slot:scripts>
        @vite(['resources/js/vtual/confirm-modal.js', 'resources/js/vtual/registry-admin.js'])
    </x-slot:scripts>
</x-adminlte4::layouts.app>
