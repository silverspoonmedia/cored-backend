@php
    $status = $claim['status'] ?? '';
    $isPending = $status === 'pending';
    $claimantName = $claim['user']['name'] ?? null;
    $claimantEmail = $claim['user']['email'] ?? null;
    $claimId = $claim['id'] ?? '';
    $platformKey = strtolower((string) ($claim['channel']['platform']['key'] ?? ''));
    $expiresRaw = $claim['expires_at'] ?? null;
    $expiresHuman = null;
    if (is_string($expiresRaw) && $expiresRaw !== '') {
        try {
            $expiresHuman = \Illuminate\Support\Carbon::parse($expiresRaw)->diffForHumans();
        } catch (\Throwable) {
            $expiresHuman = null;
        }
    }
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.claims.title')">
    <div class="vtual-admin">
        <x-adminlte4::card :title="__('web/vtual.claims.title')">
            <x-slot:actions>
                <a href="{{ route('vtual.admin.registry') }}" class="btn btn-outline-secondary btn-sm">{{ __('web/vtual.nav.registry') }}</a>
            </x-slot:actions>
            @include('adminlte4.vtual.partials.nav-admin')

            <div class="vtual-admin__stats mb-4">
                <div class="vtual-admin__stat">
                    <div class="text-muted small">{{ __('web/vtual.claims.status') }}</div>
                    <div class="mt-1"><x-adminlte4::vtual.status-badge :status="$status" /></div>
                </div>
                <div class="vtual-admin__stat">
                    <div class="text-muted small">{{ __('web/vtual.claims.attempts') }}</div>
                    <div class="vtual-admin__stat-value">{{ $claim['attempt_count'] ?? 0 }}</div>
                </div>
                <div class="vtual-admin__stat">
                    <div class="text-muted small">{{ __('web/vtual.claims.expires') }}</div>
                    <div class="small fw-semibold mb-0">
                        {{ $expiresRaw ?? '—' }}
                        @if ($expiresHuman)
                            <div class="text-muted fw-normal">{{ $expiresHuman }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <dl class="row mb-3">
                <dt class="col-sm-3">{{ __('web/vtual.claims.claimant') }}</dt>
                <dd class="col-sm-9">{{ $claimantName ?? $claim['user_id'] ?? '—' }}</dd>

                <dt class="col-sm-3">{{ __('web/vtual.claims.claimant_email') }}</dt>
                <dd class="col-sm-9">{{ $claimantEmail ?? '—' }}</dd>

                <dt class="col-sm-3">{{ __('web/vtual.claims.profile') }}</dt>
                <dd class="col-sm-9">{{ $claim['creator']['display_name'] ?? $claim['creator_id'] ?? '' }}</dd>

                <dt class="col-sm-3">{{ __('web/vtual.claims.channel') }}</dt>
                <dd class="col-sm-9">
                    <span class="badge border vtual-platform-badge--{{ $platformKey ?: 'default' }}">
                        {{ $claim['channel']['platform']['name'] ?? $claim['channel']['platform']['key'] ?? '—' }}
                    </span>
                    —
                    {{ $claim['channel']['handle'] ?? $claim['channel']['display_name'] ?? $claim['channel']['external_id'] ?? '' }}
                </dd>

                <dt class="col-sm-3">{{ __('web/vtual.claims.token') }}</dt>
                <dd class="col-sm-9">
                    <div class="vtual-admin__token-box user-select-all">{{ $claim['token'] ?? '' }}</div>
                </dd>
            </dl>

            @if ($isPending && $acceptAnywayEnabled)
                <div class="border border-warning rounded p-3">
                    <p class="small text-muted mb-2">{{ __('web/vtual.claims.accept_anyway_help') }}</p>
                    <x-adminlte4::form.ajax :action="route('vtual.admin.claims.accept-anyway', $claimId)" method="POST">
                        <button type="button"
                                class="btn btn-warning btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#vtualConfirmModal"
                                data-confirm-title="{{ __('web/vtual.confirm.accept_anyway_title') }}"
                                data-confirm-body="{{ __('web/vtual.confirm.accept_anyway_body') }}"
                                data-confirm-submit-label="{{ __('web/vtual.claims.accept_anyway') }}"
                                data-confirm-submit-class="btn-warning">
                            {{ __('web/vtual.claims.accept_anyway') }}
                        </button>
                    </x-adminlte4::form.ajax>
                </div>
            @endif
        </x-adminlte4::card>
    </div>

    @include('adminlte4.vtual.partials._confirm-modal')

    @vite('resources/css/vtual/admin.css')

    <x-slot:scripts>
        @vite('resources/js/vtual/confirm-modal.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
