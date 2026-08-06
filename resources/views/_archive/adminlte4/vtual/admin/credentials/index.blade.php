@php
    $totals = is_array($totals ?? null) ? $totals : [];
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.credentials.title')">
    <div class="vtual-admin">
        <x-adminlte4::card :title="__('web/vtual.credentials.title')">
            <x-slot:actions>
                <a href="{{ route('vtual.admin.platform-api-probe') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-bug me-1" aria-hidden="true"></i>{{ __('web/vtual.probe.open_probe') }}
                </a>
            </x-slot:actions>

            @include('adminlte4.vtual.partials.nav-admin')

            <div class="vtual-admin__intro mb-4">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <span class="vtual-admin__intro-icon" aria-hidden="true">
                        <i class="bi bi-key"></i>
                    </span>
                    <div>
                        <h2 class="h5 mb-1">{{ __('web/vtual.admin.credentials.intro.title') }}</h2>
                        <p class="text-muted mb-0">{{ __('web/vtual.admin.credentials.intro.lead') }}</p>
                    </div>
                </div>

                <div class="vtual-admin__steps">
                    @foreach (__('web/vtual.admin.credentials.intro.steps') as $index => $step)
                        <div class="vtual-admin__step">
                            <span class="vtual-admin__step-num">{{ $index + 1 }}</span>
                            <div class="fw-semibold small">{{ $step['title'] }}</div>
                            <div class="text-muted small mb-0">{{ $step['body'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="vtual-admin__stats mb-4">
                <div class="vtual-admin__stat">
                    <div class="text-muted small">{{ __('web/vtual.admin.credentials.stats.platforms_enabled') }}</div>
                    <div class="vtual-admin__stat-value">
                        {{ $totals['platforms_enabled'] ?? 0 }}<span class="fs-6 text-muted fw-normal"> / {{ $totals['platforms_total'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="vtual-admin__stat">
                    <div class="text-muted small">{{ __('web/vtual.admin.credentials.stats.credentials_active') }}</div>
                    <div class="vtual-admin__stat-value">{{ $totals['credentials_active'] ?? 0 }}</div>
                </div>
                <div class="vtual-admin__stat">
                    <div class="text-muted small">{{ __('web/vtual.admin.credentials.stats.credentials_pending') }}</div>
                    <div class="vtual-admin__stat-value">{{ $totals['credentials_pending'] ?? 0 }}</div>
                </div>
            </div>

            <h5 class="mb-3">{{ __('web/vtual.credentials.platforms') }}</h5>

            @if ($platforms->isEmpty())
                <x-adminlte4::empty-state icon="bi-key" :title="__('web/vtual.credentials.empty')" />
            @else
                <div class="row g-3">
                    @foreach ($platforms as $platform)
                        @include('adminlte4.vtual.admin.credentials._platform-card', ['platform' => $platform])
                    @endforeach
                </div>
            @endif
        </x-adminlte4::card>
    </div>

    @vite('resources/css/vtual/admin.css')
</x-adminlte4::layouts.app>
