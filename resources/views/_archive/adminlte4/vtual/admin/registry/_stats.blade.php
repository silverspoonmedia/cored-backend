<div class="vtual-admin__stats mb-4">
    <div class="vtual-admin__stat">
        <div class="text-muted small">{{ __('web/vtual.admin.registry.stats.pending') }}</div>
        <div class="vtual-admin__stat-value">{{ $pendingCount ?? 0 }}</div>
    </div>
    <div class="vtual-admin__stat">
        <div class="text-muted small">{{ __('web/vtual.admin.registry.stats.expiring_soon') }}</div>
        <div class="vtual-admin__stat-value">{{ $expiringSoonCount ?? 0 }}</div>
    </div>
    <div class="vtual-admin__stat">
        <div class="text-muted small">{{ __('web/vtual.admin.registry.stats.accept_anyway') }}</div>
        <div class="small fw-semibold mb-0">
            @if ($acceptAnywayEnabled ?? false)
                <span class="badge text-bg-warning">{{ __('web/vtual.enabled') }}</span>
            @else
                <span class="badge text-bg-secondary">{{ __('web/vtual.disabled') }}</span>
            @endif
        </div>
    </div>
</div>
