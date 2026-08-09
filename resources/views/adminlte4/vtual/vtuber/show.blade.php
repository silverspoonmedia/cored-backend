<x-adminlte4::layouts.app :title="($profile['display_name'] ?? __('web/vtual-vtuber.title')).' — '.__('web/vtual-vtuber.title')">
    @if (! empty($profile['profile_url']))
        @push('head')
            <link rel="canonical" href="{{ $profile['profile_url'] }}">
        @endpush
    @endif
    @php
        \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('vtual.vtuber.show', $profile['display_name'] ?? __('web/vtual-vtuber.title'));
    @endphp

    <div class="row g-3">
        <div class="col-md-4">
            @include(config('web.layout').'.vtual.vtuber.partials.sidebar', ['profile' => $profile])
        </div>
        <div class="col-md-8">
            @include(config('web.layout').'.vtual.vtuber.partials.content-panel', ['profile' => $profile])
        </div>
    </div>
</x-adminlte4::layouts.app>
