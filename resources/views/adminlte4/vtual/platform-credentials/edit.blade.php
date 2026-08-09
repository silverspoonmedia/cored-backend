@php
    $platformName = $platform['name'] ?? $platformId;
    $platformKey = $platform['key'] ?? '';
    $credentialId = $credential['id'] ?? '';
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.admin.credentials.edit_title').' — '.($credential['label'] ?? '')">
    <div class="vtual-admin">
        <x-adminlte4::card :title="__('web/vtual.admin.credentials.edit_title')">
            <p class="text-muted small mb-3">
                <code>{{ $platformKey }}</code> — {{ $platformName }}
                @if (! empty($credential['secret_hint']))
                    · {{ __('web/vtual.credentials.hint') }}: <code>{{ $credential['secret_hint'] }}</code>
                @endif
            </p>

            <a href="{{ route('vtual.platform-credentials.platform', $platformId) }}" class="btn btn-outline-secondary btn-sm mb-3">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('web/vtual.admin.credentials.back_to_list') }}
            </a>

            <x-adminlte4::form.ajax :action="route('vtual.platform-credentials.update', $credentialId)" method="PATCH" id="vtual-credential-edit-form">
                @include(config('web.layout').'.vtual.platform-credentials.partials.form', ['credential' => $credential])
            </x-adminlte4::form.ajax>
        </x-adminlte4::card>
    </div>

    @vite('resources/css/vtual/admin.css')
</x-adminlte4::layouts.app>
