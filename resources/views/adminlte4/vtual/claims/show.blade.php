@php
    $status = $claim['status'] ?? '';
    $isPending = $status === 'pending';
    $claimantName = $claim['user']['name'] ?? null;
    $claimantEmail = $claim['user']['email'] ?? null;
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.claims.title')">
    <x-adminlte4::card :title="__('web/vtual.claims.title')">
        @include('adminlte4.vtual.partials.nav')

        <dl class="row mb-3">
            <dt class="col-sm-3">{{ __('web/vtual.claims.claimant') }}</dt>
            <dd class="col-sm-9">
                {{ $claimantName ?? $claim['user_id'] ?? '—' }}
            </dd>

            <dt class="col-sm-3">{{ __('web/vtual.claims.claimant_email') }}</dt>
            <dd class="col-sm-9">{{ $claimantEmail ?? '—' }}</dd>

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
            <dd class="col-sm-9"><span class="badge text-bg-light border">{{ $status }}</span></dd>

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
                <form method="POST" action="{{ route('vtual.claims.verify', $claim['id']) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('web/vtual.claims.verify') }}</button>
                </form>
                <form method="POST" action="{{ route('vtual.claims.cancel', $claim['id']) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('web/vtual.claims.cancel') }}</button>
                </form>
            </div>

            @if ($canManageAny && $acceptAnywayEnabled)
                <div class="border border-warning rounded p-3">
                    <p class="small text-muted mb-2">{{ __('web/vtual.claims.accept_anyway_help') }}</p>
                    <form method="POST" action="{{ route('vtual.claims.accept-anyway', $claim['id']) }}">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm">{{ __('web/vtual.claims.accept_anyway') }}</button>
                    </form>
                </div>
            @endif
        @endif
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
