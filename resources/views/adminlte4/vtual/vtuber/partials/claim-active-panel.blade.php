@php
    $claim = $claim ?? [];
    $proofChannel = $claim['channel'] ?? null;
    $showProof = is_array($proofChannel) && ! empty($proofChannel);
    $verifyQueueStatus = $claim['verify_queue_status'] ?? null;
    $canVerify = ! empty($claim['can_verify']);
    $claimId = $claim['id'] ?? '';
@endphp

<h5 class="h6 mb-2">{{ __('web/vtual-vtuber.claim_active_heading') }}</h5>

<dl class="row small mb-3">
    <dt class="col-sm-4 {{ $showProof ? '' : 'd-none' }}" data-claim-proof-row>
        {{ __('web/vtual-vtuber.claim_proof_channel_label') }}
    </dt>
    <dd class="col-sm-8 mb-0 {{ $showProof ? '' : 'd-none' }}" data-claim-proof-channel>
        @if ($showProof)
            <span class="badge text-bg-light border">
                {{ $proofChannel['platform']['name'] ?? $proofChannel['platform']['key'] ?? '—' }}
            </span>
            <span class="ms-1">{{ $proofChannel['handle'] ?? $proofChannel['display_name'] ?? $proofChannel['external_id'] ?? '' }}</span>
            @if (! empty($proofChannel['url']))
                <a href="{{ $proofChannel['url'] }}" class="btn btn-outline-secondary btn-sm ms-2" target="_blank" rel="noopener noreferrer" data-claim-proof-open>
                    {{ __('web/vtual-vtuber.open_link') }}
                </a>
            @endif
        @endif
    </dd>
</dl>

<div class="alert alert-warning small mb-3 d-none" data-claim-failed-banner role="alert"></div>

<p class="fw-semibold mb-1">{{ __('web/vtual-vtuber.claim_token_label') }}</p>
<p class="mb-2"><code class="user-select-all" data-claim-token>{{ $claim['token'] ?? '' }}</code></p>
<p class="text-muted small mb-3">{{ __('web/vtual.claims.token_help') }}</p>

<dl class="row small mb-3">
    <dt class="col-sm-4">{{ __('web/vtual-vtuber.claim_attempts_remaining') }}</dt>
    <dd class="col-sm-8 mb-0" data-claim-attempts>{{ $claim['attempts_remaining'] ?? '—' }}</dd>
    <dt class="col-sm-4 {{ empty($verifyQueueStatus) ? 'd-none' : '' }}" data-claim-queue-row>
        {{ __('web/vtual-vtuber.claim_queue_status_label') }}
    </dt>
    <dd class="col-sm-8 mb-0 {{ empty($verifyQueueStatus) ? 'd-none' : '' }}" data-claim-queue-row>
        <span class="badge text-bg-info" data-claim-queue-badge>
            @if (! empty($verifyQueueStatus))
                {{ __('web/vtual-vtuber.claim_queue_status_'.$verifyQueueStatus) }}
            @endif
        </span>
    </dd>
</dl>

<p class="text-muted small mb-3" data-claim-queue-notice>
    @if (! empty($claimRealtime['enabled']))
        {{ __('web/vtual-vtuber.claim_verify_queue_notice_realtime') }}
    @else
        {{ __('web/vtual-vtuber.claim_verify_queue_notice_degraded') }}
    @endif
</p>

<div class="d-flex flex-wrap gap-2">
    <div class="{{ $canVerify ? '' : 'd-none' }}" data-claim-verify-wrap>
        <x-adminlte4::form.ajax
            :action="route('vtual.vtuber.claim.verify', $slug)"
            method="POST"
            class="d-inline"
            :showAlert="false"
            data-claim-verify-form
        >
            <input type="hidden" name="claim_id" value="{{ $claimId }}">
            <x-adminlte4::form.submit
                :label="__('web/vtual-vtuber.claim_verify_button')"
                class="btn btn-primary btn-sm"
            />
        </x-adminlte4::form.ajax>
    </div>

    <x-adminlte4::form.ajax
        :action="route('vtual.vtuber.claim.cancel', $slug)"
        method="POST"
        class="d-inline"
        :showAlert="false"
        data-claim-cancel-form
    >
        <input type="hidden" name="claim_id" value="{{ $claimId }}">
        <x-adminlte4::form.submit
            :label="__('web/vtual-vtuber.claim_cancel')"
            class="btn btn-outline-danger btn-sm"
        />
    </x-adminlte4::form.ajax>
</div>
