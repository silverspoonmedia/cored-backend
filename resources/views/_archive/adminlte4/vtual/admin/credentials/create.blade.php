@php
    $platformName = $platform['name'] ?? $platformId;
    $platformKey = $platform['key'] ?? '';
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.admin.credentials.create_title').' — '.$platformName">
    <div class="vtual-admin">
        <x-adminlte4::card :title="__('web/vtual.admin.credentials.create_title')">
            <p class="text-muted small mb-3">
                <code>{{ $platformKey }}</code> — {{ $platformName }}
            </p>

            <a href="{{ route('vtual.admin.credentials.platform', $platformId) }}" class="btn btn-outline-secondary btn-sm mb-3">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('web/vtual.admin.credentials.back_to_list') }}
            </a>

            <x-adminlte4::form.ajax :action="route('vtual.admin.credentials.store', $platformId)" method="POST" id="vtual-credential-create-form">
                @include('adminlte4.vtual.admin.credentials._form', ['credential' => null])
            </x-adminlte4::form.ajax>
        </x-adminlte4::card>
    </div>

    @vite('resources/css/vtual/admin.css')

    <x-slot:scripts>
        @vite('resources/js/vtual/credential-form.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
