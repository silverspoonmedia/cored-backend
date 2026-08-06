<x-adminlte4::layouts.app :title="($profile['display_name'] ?? __('web/vtual-vtuber.title')).' — '.__('web/vtual-vtuber.title')">
    @if (! empty($profile['profile_url']))
        @push('head')
            <link rel="canonical" href="{{ $profile['profile_url'] }}">
        @endpush
    @endif
    @php
        \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('vtual.vtuber.show', $profile['display_name'] ?? __('web/vtual-vtuber.title'));
    @endphp
    <x-adminlte4::card :title="$profile['display_name'] ?? __('web/vtual-vtuber.title')">
        @include(config('web.layout').'.vtual.vtuber._bio', ['profile' => $profile])

        <hr class="my-4">

        @include(config('web.layout').'.vtual.vtuber._channels', ['profile' => $profile])

        <hr class="my-4">

        @include(config('web.layout').'.vtual.vtuber._contents', ['profile' => $profile])
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
