<x-adminlte4::layouts.app :title="($creator['display_name'] ?? __('web/vtual.profiles.manage_title')).' — '.__('web/vtual.title')">
    <x-adminlte4::card :title="$creator['display_name'] ?? __('web/vtual.profiles.manage_title')">
        <x-slot:actions>
            <a href="{{ route('vtual.profiles.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('web/vtual.back') }}</a>
        </x-slot:actions>

        @include('adminlte4.vtual.partials.nav')

        @include('adminlte4.vtual.partials._profile-meta-form')
        @include('adminlte4.vtual.partials._profile-merge-form')
        @include('adminlte4.vtual.partials._profile-links')
        @include('adminlte4.vtual.partials._profile-channels')
        @include('adminlte4.vtual.partials._profile-content-stub')
    </x-adminlte4::card>

    @include('adminlte4.vtual.partials._confirm-modal')

    <x-slot:scripts>
        @vite('resources/js/vtual/confirm-modal.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
