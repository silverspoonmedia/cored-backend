@php
    $platformName = $platform['name'] ?? $platformId;
    $platformKey = $platform['key'] ?? '';
    $statusLabels = [
        'pending' => __('web/vtual.credentials.status_pending'),
        'active' => __('web/vtual.credentials.status_active'),
        'revoked' => __('web/vtual.credentials.status_revoked'),
    ];
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.credentials.list').' — '.$platformName">
    <div class="vtual-admin">
        <x-adminlte4::card :title="__('web/vtual.credentials.list').': '.$platformName">
            <x-slot:actions>
                <a href="{{ route('vtual.platform-credentials.create', $platformId) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('web/vtual.credentials.add') }}
                </a>
            </x-slot:actions>

            <p class="text-muted small mb-3">
                <code>{{ $platformKey }}</code>
                — {{ __('web/vtual.credentials.subtitle') }}
            </p>

            <a href="{{ route('vtual.platform-credentials.index') }}" class="btn btn-outline-secondary btn-sm mb-3">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('web/vtual-platform-credentials.back_to_platforms') }}
            </a>

            <div class="table-responsive" id="vtual-credentials-table-wrapper" data-status-labels='@json($statusLabels)'>
                <table id="vtual-credentials-table" class="table table-hover align-middle mb-0 w-100" aria-label="{{ __('web/vtual.table.caption_credentials') }}">
                    <caption class="visually-hidden">{{ __('web/vtual.table.caption_credentials') }}</caption>
                    <thead>
                        <tr>
                            <th>{{ __('web/vtual.credentials.label') }}</th>
                            <th>{{ __('web/vtual.credentials.type') }}</th>
                            <th>{{ __('web/vtual.status') }}</th>
                            <th>{{ __('web/vtual.credentials.priority') }}</th>
                            <th>{{ __('web/vtual.credentials.hint') }}</th>
                            <th>{{ __('web/vtual.credentials.verified_at') }}</th>
                            <th>{{ __('web/vtual.credentials.runtime_expires') }}</th>
                            <th class="text-end">{{ __('web/vtual.table.label_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($credentials as $credential)
                            <tr data-credential-id="{{ $credential['id'] ?? '' }}">
                                <td>{{ $credential['label'] ?? '' }}</td>
                                <td><code>{{ $credential['type'] ?? '' }}</code></td>
                                <td data-credential-field="status">
                                    <x-adminlte4::vtual.status-badge :status="$credential['status'] ?? ''" :label="__('web/vtual.credentials.status_'.($credential['status'] ?? ''))" />
                                </td>
                                <td>{{ $credential['priority'] ?? '' }}</td>
                                <td><code>{{ $credential['secret_hint'] ?? '—' }}</code></td>
                                <td class="small text-muted" data-credential-field="verified_at">{{ $credential['verified_at'] ?? '—' }}</td>
                                <td class="small text-muted" data-credential-field="runtime_expires">
                                    @if (! empty($credential['has_runtime']) && ! empty($credential['runtime_expires_at']))
                                        {{ $credential['runtime_expires_at'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    @include(config('web.layout').'.vtual.platform-credentials._credential_actions', [
                                        'credential' => $credential,
                                        'platformId' => $platformId,
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($credentials->isEmpty())
                <x-adminlte4::empty-state icon="bi-key" :title="__('web/vtual.credentials.empty')" class="mt-3" />
            @endif

            <script type="application/json" data-vtual-credentials-dt>
                {!! json_encode([
                    'emptyTable' => __('web/vtual.admin.credentials.datatable.empty'),
                    'zeroRecords' => __('web/vtual.admin.credentials.datatable.zero_records'),
                    'search' => __('web/vtual.admin.credentials.datatable.search'),
                    'searchPlaceholder' => __('web/vtual.admin.credentials.datatable.search_placeholder'),
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
            </script>
        </x-adminlte4::card>
    </div>

    @include('adminlte4.vtual.partials._confirm-modal')

    @vite('resources/css/vtual/admin.css')

    <x-slot:scripts>
        @vite(['resources/js/vtual/confirm-modal.js', 'resources/js/vtual/credentials-platform.js'])
    </x-slot:scripts>
</x-adminlte4::layouts.app>
