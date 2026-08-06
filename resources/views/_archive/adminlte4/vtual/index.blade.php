@php
    $user = auth()->user();
    $canClaim = $user?->can('claim vtual creator') ?? false;
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.title')">
    <x-adminlte4::card :title="__('web/vtual.title')">
        <p class="text-muted small mb-3">{{ __('web/vtual.foundation_note') }}</p>
        @include('adminlte4.vtual.partials.nav-app')

        <div class="border rounded p-3 mb-4">
            <h6 class="mb-3">{{ __('web/vtual.nav.hub_title') }}</h6>
            <div class="row g-2">
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-2 h-100">
                        <strong class="d-block mb-1">{{ __('web/vtual.nav.overview') }}</strong>
                        <p class="small text-muted mb-0">{{ __('web/vtual.nav.hub_overview') }}</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('vtual.profiles.index') }}" class="text-decoration-none text-body">
                        <div class="border rounded p-2 h-100 border-info">
                            <strong class="d-block mb-1">{{ __('web/vtual.nav.profiles') }}</strong>
                            <p class="small text-muted mb-0">{{ __('web/vtual.nav.hub_profiles') }}</p>
                        </div>
                    </a>
                </div>
                @if ($canClaim)
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('vtual.claims.index') }}" class="text-decoration-none text-body">
                            <div class="border rounded p-2 h-100 border-primary">
                                <strong class="d-block mb-1">{{ __('web/vtual.nav.claim') }}</strong>
                                <p class="small text-muted mb-0">{{ __('web/vtual.nav.hub_claim') }}</p>
                            </div>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <h5 class="mb-2">{{ __('web/vtual.platforms') }}</h5>
        <ul class="list-group list-group-flush border rounded">
            @foreach ($platforms as $platform)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ $platform['name'] ?? $platform['key'] ?? '' }}</span>
                    @if (!empty($platform['enabled']))
                        <span class="badge text-bg-success">{{ __('web/vtual.enabled') }}</span>
                    @else
                        <span class="badge text-bg-secondary">{{ __('web/vtual.disabled') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
