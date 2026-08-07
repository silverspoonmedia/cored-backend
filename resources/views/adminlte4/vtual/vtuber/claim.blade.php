@php
    $displayName = $profile['display_name'] ?? __('web/vtual-vtuber.title');
    $channels = $profile['channels'] ?? [];
    $profileUrl = $profileUrl ?? $profile['profile_url'] ?? route('vtual.vtuber.show', $profile['slug'] ?? '');
    $creatorId = $profile['id'] ?? '';
    $slug = $profile['slug'] ?? '';
    $hasActiveClaim = is_array($activeClaim) && ($activeClaim['status'] ?? '') === 'pending';
    $blockedUntil = $profile['claim_blocked_until'] ?? $profile['claim_blocked_until_formatted']
        ?? ($activeClaim['blocked_until'] ?? $activeClaim['blocked_until_formatted'] ?? null);
    $claimConfig = [
        'profileUrl' => $profileUrl,
        'realtime' => $claimRealtime ?? [],
        'messages' => [
            'connected' => __('web/vtual-vtuber.claim_realtime_connected'),
            'connecting' => __('web/vtual-vtuber.claim_realtime_connecting'),
            'realtimeNotice' => __('web/vtual-vtuber.claim_verify_queue_notice_realtime'),
            'degradedNotice' => __('web/vtual-vtuber.claim_verify_queue_notice_degraded'),
            'started' => __('web/vtual.ajax.claim_started'),
            'openLink' => __('web/vtual-vtuber.open_link'),
            'proofChannelLabel' => __('web/vtual-vtuber.claim_proof_channel_label'),
            'queued' => __('web/vtual-vtuber.claim_verify_queued'),
            'processing' => __('web/vtual-vtuber.claim_progress_processing'),
            'verified' => __('web/vtual-vtuber.claim_progress_verified'),
            'failed' => __('web/vtual-vtuber.claim_progress_failed'),
            'maxAttemptsBlocked' => __('web/vtual-vtuber.claim_progress_max_attempts'),
            'blockedUntil' => __('web/vtual-vtuber.claim_blocked_until', ['until' => ':until']),
            'queue_pending' => __('web/vtual-vtuber.claim_queue_status_pending'),
            'queue_processing' => __('web/vtual-vtuber.claim_queue_status_processing'),
        ],
    ];
@endphp

<x-adminlte4::layouts.app :title="__('web/vtual-vtuber.claim_page_title').' — '.$displayName">
    @php
        \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('vtual.vtuber.claim', $displayName);
    @endphp

    @vite('resources/js/vtual/claim-realtime.js')

    <div id="vtual-claim-root" class="card">
        <script type="application/json" data-claim-config>@json($claimConfig)</script>

        <div class="card-header d-flex align-items-center gap-2">
            <h3 class="card-title mb-0 flex-grow-1 float-none">{{ __('web/vtual-vtuber.claim_page_title') }}</h3>
            <div class="card-tools float-none me-0 flex-shrink-0">
                <a href="{{ $profileUrl }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('web/vtual-vtuber.claim_back_to_profile') }}
                </a>
            </div>
        </div>

        <div class="card-body">
            <p class="text-muted small mb-3">{{ __('web/vtual-vtuber.claim_page_subtitle') }}</p>

            @if (! empty($claimRealtime['enabled']))
                <div
                    class="d-flex align-items-center gap-2 small text-secondary mb-3"
                    data-claim-realtime-bar
                    role="status"
                    aria-live="polite"
                >
                    <i class="bi bi-broadcast" data-claim-realtime-icon aria-hidden="true"></i>
                    <span data-claim-realtime-status>{{ __('web/vtual-vtuber.claim_realtime_connecting') }}</span>
                </div>
            @endif

            <div class="alert alert-info small mb-3 d-none" data-claim-degraded role="status">
                {{ __('web/vtual-vtuber.claim_realtime_degraded') }}
            </div>

            @if (! empty($blockedUntil))
                <div class="alert alert-warning small mb-3" data-claim-blocked-banner role="alert">
                    {{ __('web/vtual-vtuber.claim_blocked_until', ['until' => $profile['claim_blocked_until_formatted'] ?? $blockedUntil]) }}
                </div>
            @endif

            <div
                class="border rounded p-3 mb-4 {{ $hasActiveClaim ? '' : 'd-none' }}"
                id="vtual-active-claim-panel"
                data-claim-panel
            >
                @include('adminlte4.vtual.vtuber.partials.claim-active-panel', [
                    'claim' => $hasActiveClaim ? $activeClaim : [],
                    'slug' => $slug,
                    'claimRealtime' => $claimRealtime ?? [],
                ])
            </div>

            <template data-claim-panel-template>
                <div class="border rounded p-3 mb-4" data-claim-panel>
                    @include('adminlte4.vtual.vtuber.partials.claim-active-panel', [
                        'claim' => [],
                        'slug' => $slug,
                        'claimRealtime' => $claimRealtime ?? [],
                    ])
                </div>
            </template>

            <div
                class="{{ ($hasActiveClaim || ! empty($blockedUntil)) ? 'd-none' : '' }}"
                data-claim-channels-section
            >
                <h5 class="h6 mb-2">{{ __('web/vtual-vtuber.claim_channels_heading') }}</h5>
                @if (count($channels) === 0)
                    <p class="text-muted small mb-4">{{ __('web/vtual-vtuber.claim_no_channels_note') }}</p>
                @else
                    <ul class="list-group list-group-flush mb-4">
                        @foreach ($channels as $channel)
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                    <span>
                                        <span class="badge text-bg-light border">
                                            {{ $channel['platform']['name'] ?? $channel['platform']['key'] ?? '—' }}
                                        </span>
                                        <span class="ms-1">{{ $channel['handle'] ?? $channel['display_name'] ?? $channel['external_id'] ?? '' }}</span>
                                    </span>
                                    <div class="d-flex gap-2">
                                        @if (! empty($channel['url']))
                                            <a href="{{ $channel['url'] }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                                                {{ __('web/vtual-vtuber.open_link') }}
                                            </a>
                                        @endif
                                        <x-adminlte4::form.ajax
                                            :action="route('vtual.vtuber.claim.start', $slug)"
                                            method="POST"
                                            class="d-inline"
                                            :showAlert="false"
                                            data-claim-start-form
                                        >
                                            <input type="hidden" name="creator_id" value="{{ $creatorId }}">
                                            <input type="hidden" name="channel_id" value="{{ $channel['id'] ?? '' }}">
                                            <x-adminlte4::form.submit
                                                :label="__('web/vtual-vtuber.claim_verify_with_channel')"
                                                class="btn btn-primary btn-sm"
                                            />
                                        </x-adminlte4::form.ajax>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <details class="small">
                <summary class="fw-semibold mb-2">{{ __('web/vtual-vtuber.claim_steps_heading') }}</summary>
                <ol class="mb-0 mt-2">
                    <li>{{ __('web/vtual-vtuber.claim_step_1') }}</li>
                    <li>{{ __('web/vtual-vtuber.claim_step_2') }}</li>
                    <li>{{ __('web/vtual-vtuber.claim_step_3') }}</li>
                    <li>{{ __('web/vtual-vtuber.claim_step_4') }}</li>
                    <li>{{ __('web/vtual-vtuber.claim_step_5') }}</li>
                </ol>
            </details>
        </div>
    </div>
</x-adminlte4::layouts.app>
