@php
    $status = $claim['status'] ?? '';
    $isPending = $status === 'pending';
    $claimId = $claim['id'] ?? '';
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.claims.title')">
    <x-adminlte4::card :title="__('web/vtual.claims.title')">
        <x-slot:actions>
            <a href="{{ route('vtual.claims.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('web/vtual.back') }}</a>
        </x-slot:actions>
        @include('adminlte4.vtual.partials.nav-app')

        <dl class="row mb-3">
            <dt class="col-sm-3">{{ __('web/vtual.claims.profile') }}</dt>
            <dd class="col-sm-9">{{ $claim['creator']['display_name'] ?? $claim['creator_id'] ?? '' }}</dd>

            <dt class="col-sm-3">{{ __('web/vtual.claims.channel') }}</dt>
            <dd class="col-sm-9">
                <span class="badge text-bg-light border">
                    {{ $claim['channel']['platform']['name'] ?? $claim['channel']['platform']['key'] ?? '—' }}
                </span>
                —
                {{ $claim['channel']['handle'] ?? $claim['channel']['display_name'] ?? $claim['channel']['external_id'] ?? '' }}
            </dd>

            <dt class="col-sm-3">{{ __('web/vtual.claims.status') }}</dt>
            <dd class="col-sm-9"><x-adminlte4::vtual.status-badge :status="$status" /></dd>

            <dt class="col-sm-3">{{ __('web/vtual.claims.token') }}</dt>
            <dd class="col-sm-9"><code class="user-select-all">{{ $claim['token'] ?? '' }}</code></dd>

            <dt class="col-sm-3">{{ __('web/vtual.claims.attempts') }}</dt>
            <dd class="col-sm-9">{{ $claim['attempt_count'] ?? 0 }}</dd>

            <dt class="col-sm-3">{{ __('web/vtual.claims.expires') }}</dt>
            <dd class="col-sm-9">
                {{ $claim['expires_at'] ?? '—' }}
                <div class="small text-muted">{{ __('web/vtual.claims.expires_help') }}</div>
            </dd>
        </dl>

        @if ($isPending)
            <p class="text-muted small">{{ __('web/vtual.claims.token_help') }}</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <x-adminlte4::form.ajax :action="route('vtual.claims.verify', $claimId)" method="POST" class="d-inline">
                    <x-adminlte4::form.submit :label="__('web/vtual.claims.verify')" class="btn btn-primary btn-sm" />
                </x-adminlte4::form.ajax>
                <x-adminlte4::form.ajax :action="route('vtual.claims.cancel', $claimId)" method="POST" class="d-inline">
                    <button type="button"
                            class="btn btn-outline-danger btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#vtualConfirmModal"
                            data-confirm-title="{{ __('web/vtual.confirm.cancel_claim_title') }}"
                            data-confirm-body="{{ __('web/vtual.confirm.cancel_claim_body') }}"
                            data-confirm-submit-label="{{ __('web/vtual.claims.cancel') }}">
                        {{ __('web/vtual.claims.cancel') }}
                    </button>
                </x-adminlte4::form.ajax>
            </div>
        @endif
    </x-adminlte4::card>

    @include('adminlte4.vtual.partials._confirm-modal')

    <x-slot:scripts>
        @vite('resources/js/vtual/confirm-modal.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
