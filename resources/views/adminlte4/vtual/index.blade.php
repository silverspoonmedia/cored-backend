@php
    $user = auth()->user();
    $canManageAny = $user?->can('manage any vtual resource') ?? false;
    $canClaim = $user?->can('claim vtual creator') ?? false;
    $canManageCredentials = $user?->can('manage vtual platform credentials') ?? false;
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.title')">
    <x-adminlte4::card :title="__('web/vtual.title')">
        <p class="text-muted small mb-3">{{ __('web/vtual.foundation_note') }}</p>
        @include('adminlte4.vtual.partials.nav')

        <div class="border rounded p-3 mb-4">
            <h6 class="mb-3">{{ __('web/vtual.nav.hub_title') }}</h6>
            <div class="row g-2">
                <div class="col-md-6 col-lg-3">
                    <div class="border rounded p-2 h-100">
                        <strong class="d-block mb-1">{{ __('web/vtual.nav.overview') }}</strong>
                        <p class="small text-muted mb-0">{{ __('web/vtual.nav.hub_overview') }}</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('vtual.profiles.index') }}" class="text-decoration-none text-body">
                        <div class="border rounded p-2 h-100 border-info">
                            <strong class="d-block mb-1">{{ __('web/vtual.nav.profiles') }}</strong>
                            <p class="small text-muted mb-0">{{ __('web/vtual.nav.hub_profiles') }}</p>
                        </div>
                    </a>
                </div>
                @if ($canClaim)
                    <div class="col-md-6 col-lg-3">
                        <a href="{{ route('vtual.claims.index') }}" class="text-decoration-none text-body">
                            <div class="border rounded p-2 h-100">
                                <strong class="d-block mb-1">{{ __('web/vtual.nav.claim') }}</strong>
                                <p class="small text-muted mb-0">{{ __('web/vtual.nav.hub_claim') }}</p>
                            </div>
                        </a>
                    </div>
                @endif
                @if ($canManageAny)
                    <div class="col-md-6 col-lg-3">
                        <a href="{{ route('vtual.claims.staff') }}" class="text-decoration-none text-body">
                            <div class="border rounded p-2 h-100 border-warning">
                                <strong class="d-block mb-1">{{ __('web/vtual.nav.registry') }}</strong>
                                <p class="small text-muted mb-0">{{ __('web/vtual.nav.hub_registry') }}</p>
                            </div>
                        </a>
                    </div>
                @endif
                @if ($canManageCredentials)
                    <div class="col-md-6 col-lg-3">
                        <a href="{{ route('vtual.admin.credentials.index') }}" class="text-decoration-none text-body">
                            <div class="border rounded p-2 h-100">
                                <strong class="d-block mb-1">{{ __('web/vtual.nav.credentials') }}</strong>
                                <p class="small text-muted mb-0">{{ __('web/vtual.nav.hub_credentials') }}</p>
                            </div>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <h5 class="mb-2">{{ __('web/vtual.creators') }}</h5>
                @if ($canManageAny)
                    <p class="text-muted small">{{ __('web/vtual.claims.orphan_help') }}</p>
                    <p class="mb-3">
                        <a href="{{ route('vtual.claims.staff') }}" class="btn btn-warning btn-sm">
                            {{ __('web/vtual.claims.orphan_create') }}
                        </a>
                    </p>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/vtual.display_name') }}</th>
                                <th>Slug</th>
                                <th>{{ __('web/vtual.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($creators as $creator)
                                <tr>
                                    <td>{{ $creator['display_name'] ?? '' }}</td>
                                    <td><code>{{ $creator['slug'] ?? '' }}</code></td>
                                    <td><span class="badge text-bg-light border">{{ $creator['status'] ?? '' }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('vtual.profiles.show', $creator['id']) }}" class="btn btn-outline-info btn-sm">
                                            {{ __('web/vtual.manage') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-adminlte4::empty-state icon="bi-broadcast" :title="__('web/vtual.empty_creators')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-4">
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
            </div>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
