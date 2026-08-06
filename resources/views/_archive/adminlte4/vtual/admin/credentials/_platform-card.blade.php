@php
    $platformKey = strtolower((string) ($platform['key'] ?? ''));
    $icon = match ($platformKey) {
        'youtube' => 'bi-youtube',
        'twitch' => 'bi-twitch',
        'kick' => 'bi-broadcast',
        default => 'bi-hdd-network',
    };
@endphp
<div class="col-12 col-md-6 col-xl-4">
    <div class="card h-100 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="vtual-admin__platform-icon" aria-hidden="true">
                    <i class="bi {{ $icon }}"></i>
                </span>
                <div class="flex-grow-1">
                    <h3 class="h6 mb-1">{{ $platform['name'] ?? '' }}</h3>
                    <code class="small">{{ $platform['key'] ?? '' }}</code>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-1 mb-3">
                @if (! empty($platform['enabled']))
                    <span class="badge text-bg-success">{{ __('web/vtual.enabled') }}</span>
                @else
                    <span class="badge text-bg-secondary">{{ __('web/vtual.disabled') }}</span>
                @endif
                @if (! empty($platform['supports_live']))
                    <span class="badge text-bg-light border">{{ __('web/vtual.admin.credentials.platform_card.live') }}</span>
                @endif
                @if (! empty($platform['supports_vod']))
                    <span class="badge text-bg-light border">{{ __('web/vtual.admin.credentials.platform_card.vod') }}</span>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2 small">
                <span class="badge text-bg-primary">
                    {{ trans_choice('web/vtual.admin.credentials.platform_card.active_count', $platform['credentials_active'] ?? 0, ['count' => $platform['credentials_active'] ?? 0]) }}
                </span>
                <span class="badge text-bg-warning text-dark">
                    {{ trans_choice('web/vtual.admin.credentials.platform_card.pending_count', $platform['credentials_pending'] ?? 0, ['count' => $platform['credentials_pending'] ?? 0]) }}
                </span>
            </div>
        </div>
        <div class="card-footer bg-body border-top-0 pt-0">
            <a href="{{ route('vtual.admin.credentials.platform', $platform['id']) }}" class="btn btn-outline-primary btn-sm w-100">
                {{ __('web/vtual.credentials.manage') }}
            </a>
        </div>
    </div>
</div>
