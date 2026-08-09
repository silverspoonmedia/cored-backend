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

        <div data-profile-route-key="{{ $routeKey }}"
             data-profile-can-mutate="{{ $canMutate ? '1' : '0' }}"
             data-profile-can-accept-anyway="{{ ($canAcceptAnyway ?? false) ? '1' : '0' }}"
             data-channel-refresh-url="{{ route('vtual.profiles.channels.refresh', [$routeKey, '__CHANNEL__']) }}"
             data-channel-archive-url="{{ route('vtual.profiles.channels.archive', [$routeKey, '__CHANNEL__']) }}"
             data-channel-restore-url="{{ route('vtual.profiles.channels.restore', [$routeKey, '__CHANNEL__']) }}"
             data-channel-promote-url="{{ route('vtual.profiles.channels.promote', [$routeKey, '__CHANNEL__']) }}"
             data-link-destroy-url="{{ route('vtual.profiles.links.destroy', [$routeKey, '__LINK__']) }}">
        <div data-profile-meta data-profile-display-name="{{ $creator['display_name'] ?? '' }}" data-profile-slug="{{ $creator['slug'] ?? '' }}">
        @include(config('web.layout').'.vtual.profiles.partials.meta-form')
        </div>
        @include(config('web.layout').'.vtual.profiles.partials.merge-form')
        @include(config('web.layout').'.vtual.profiles.partials.links')
        @include(config('web.layout').'.vtual.profiles.partials.channels')
        @include(config('web.layout').'.vtual.profiles.partials.content-stub')
        </div>
    </x-adminlte4::card>

    @include(config('web.layout').'.vtual.partials.confirm-modal')

    <x-slot:scripts>
        @vite('resources/js/vtual/confirm-modal.js')
        @vite('resources/js/vtual/profiles-live.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
