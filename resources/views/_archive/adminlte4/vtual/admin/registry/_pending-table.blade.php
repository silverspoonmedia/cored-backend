<h5 class="mb-2">{{ __('web/vtual.claims.pending_queue') }}</h5>
<p class="text-muted small mb-3">{{ __('web/vtual.claims.expires_help') }}</p>

<div class="table-responsive">
    <table id="vtual-registry-pending-table" class="table table-hover align-middle mb-0 w-100" aria-label="{{ __('web/vtual.table.caption_pending_claims') }}">
        <caption class="visually-hidden">{{ __('web/vtual.table.caption_pending_claims') }}</caption>
        <thead>
            <tr>
                <th>{{ __('web/vtual.admin.registry.columns.claimant') }}</th>
                <th>{{ __('web/vtual.admin.registry.columns.profile') }}</th>
                <th>{{ __('web/vtual.admin.registry.columns.channel') }}</th>
                <th>{{ __('web/vtual.admin.registry.columns.attempts') }}</th>
                <th>{{ __('web/vtual.admin.registry.columns.expires') }}</th>
                <th class="text-end">{{ __('web/vtual.table.label_actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pending as $claim)
                @php
                    $platformKey = strtolower((string) ($claim['channel']['platform']['key'] ?? ''));
                    $expiresRaw = $claim['expires_at'] ?? null;
                    $expiresAt = null;
                    $expiresHuman = '—';
                    $rowWarning = false;
                    if (is_string($expiresRaw) && $expiresRaw !== '') {
                        try {
                            $expiresAt = \Illuminate\Support\Carbon::parse($expiresRaw);
                            $expiresHuman = $expiresAt->diffForHumans();
                            $rowWarning = $expiresAt->isFuture() && $expiresAt->lte(now()->addHours(24));
                        } catch (\Throwable) {
                            $expiresHuman = $expiresRaw;
                        }
                    }
                @endphp
                <tr @class(['table-warning' => $rowWarning])>
                    <td>
                        {{ $claim['user']['name'] ?? $claim['user']['email'] ?? $claim['user_id'] ?? '—' }}
                        @if (!empty($claim['user']['email']))
                            <div class="small text-muted">{{ $claim['user']['email'] }}</div>
                        @endif
                    </td>
                    <td>
                        {{ $claim['creator']['display_name'] ?? $claim['creator_id'] ?? '' }}
                        @if (!empty($claim['creator']['slug']))
                            <div class="small"><code>{{ $claim['creator']['slug'] }}</code></div>
                        @endif
                    </td>
                    <td class="small">
                        <span class="badge border vtual-platform-badge--{{ $platformKey ?: 'default' }}">
                            {{ $claim['channel']['platform']['name'] ?? $claim['channel']['platform']['key'] ?? '—' }}
                        </span>
                        <div class="mt-1">
                            {{ $claim['channel']['handle'] ?? $claim['channel']['display_name'] ?? $claim['channel']['external_id'] ?? '' }}
                        </div>
                    </td>
                    <td>{{ $claim['attempt_count'] ?? 0 }}</td>
                    <td class="small">
                        {{ $expiresRaw ?? '—' }}
                        @if ($expiresHuman !== '—')
                            <div class="text-muted">{{ $expiresHuman }}</div>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="btn-group" role="group" aria-label="{{ __('web/vtual.table.label_actions') }}">
                            <a href="{{ route('vtual.admin.claims.show', $claim['id']) }}" class="btn btn-outline-primary btn-sm">
                                {{ __('web/vtual.view') }}
                            </a>
                            @if ($acceptAnywayEnabled)
                                <x-adminlte4::form.ajax :action="route('vtual.admin.claims.accept-anyway', $claim['id'])" method="POST" class="d-inline">
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
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($pending->isEmpty())
    <x-adminlte4::empty-state icon="bi-inbox" :title="__('web/vtual.claims.empty_pending')" class="mt-3" />
@endif

<script type="application/json" data-vtual-registry-dt>
    {!! json_encode([
        'emptyTable' => __('web/vtual.admin.registry.datatable.empty'),
        'zeroRecords' => __('web/vtual.admin.registry.datatable.zero_records'),
        'search' => __('web/vtual.admin.registry.datatable.search'),
        'searchPlaceholder' => __('web/vtual.admin.registry.datatable.search_placeholder'),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
