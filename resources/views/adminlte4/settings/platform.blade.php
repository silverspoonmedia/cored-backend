@php
    $titleKey = match ($group ?? 'platform') {
        'file-hosting' => 'web/settings.groups.file_hosting',
        'microstock' => 'web/settings.groups.microstock',
        'vtual' => 'web/settings.groups.vtual',
        default => 'web/settings.groups.platform',
    };
@endphp

<x-adminlte4::layouts.app :title="__($titleKey)">
    <x-adminlte4::card :title="__($titleKey)">
        @include(config('web.layout').'.settings._form', [
            'group' => $group,
            'settings' => $settings ?? [],
            'action' => route('settings.update', $group),
        ])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
