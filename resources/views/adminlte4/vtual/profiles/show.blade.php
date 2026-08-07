<x-adminlte4::layouts.app :title="($creator['display_name'] ?? __('web/vtual.profiles.manage_title')).' — '.__('web/vtual-profiles.page_title')">
    @php
        $routeKey = $creatorRouteKey ?? $creator['slug'] ?? $creator['id'] ?? '';
        \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('vtual.profiles.show', $creator['display_name'] ?? __('web/vtual.profiles.manage_title'));
    @endphp

    <x-adminlte4::card :title="$creator['display_name'] ?? __('web/vtual.profiles.manage_title')">
        <x-slot:actions>
            <a href="{{ route('vtual.profiles.index') }}" class="btn btn-outline-secondary btn-sm me-1">
                {{ __('web/vtual-profiles.page_title') }}
            </a>
            @if (! empty($creator['slug']))
                <a href="{{ route('vtual.vtuber.show', $creator['slug']) }}" class="btn btn-outline-primary btn-sm">
                    {{ __('web/vtual-profiles.view_profile') }}
                </a>
            @endif
        </x-slot:actions>

        @include(config('web.layout').'.vtual.profiles.partials._meta-form')
        @include(config('web.layout').'.vtual.profiles.partials._merge-form')
        @include(config('web.layout').'.vtual.profiles.partials._links')
        @include(config('web.layout').'.vtual.profiles.partials._channels')
        @include(config('web.layout').'.vtual.profiles.partials._content-stub')
    </x-adminlte4::card>

    @include(config('web.layout').'.vtual.partials._confirm-modal')

    <x-slot:scripts>
        @vite('resources/js/vtual/confirm-modal.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
