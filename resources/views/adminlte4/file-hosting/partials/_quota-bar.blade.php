@php
    /**
     * Quota progress bar.
     *
     * @var array|null $quota  Resource-shaped usage payload with raw + *_human companions
     */
    if (! $quota) {
        return;
    }

    $used = (int) ($quota['used_bytes'] ?? 0);
    $limit = (int) ($quota['quota_bytes'] ?? 0);
    $state = (string) ($quota['state'] ?? 'free');
    $pct = $limit > 0 ? min(100, ($used / $limit) * 100) : 0;
    $fillClass = $pct >= 90 ? 'is-danger' : ($pct >= 75 ? 'is-warn' : '');

    $usedLabel = $quota['used_bytes_human'] ?? '0 B';
    $limitLabel = $quota['quota_bytes_human'] ?? null;

    $stateLabel = match ($state) {
        'paid' => __('web/file-hosting.collab.badge_paid'),
        'grace' => __('web/file-hosting.collab.badge_grace'),
        default => __('web/file-hosting.collab.badge_free'),
    };
    $stateClass = match ($state) {
        'paid' => 'text-bg-success',
        'grace' => 'text-bg-warning',
        default => 'text-bg-secondary',
    };
@endphp
<div class="fh-quota">
    <div class="fh-quota__header">
        <span class="fh-quota__label">
            {{ __('web/file-hosting.quota_used') }}
            <span class="badge {{ $stateClass }} ms-1" style="font-size: .65rem; vertical-align: middle;">{{ $stateLabel }}</span>
        </span>
        <span class="fh-quota__value">
            {{ $usedLabel }}
            @if ($limit > 0)
                / {{ $limitLabel ?? '0 B' }}
            @else
                / {{ __('web/file-hosting.quota_unlimited') }}
            @endif
        </span>
    </div>
    <div class="fh-quota__track">
        <div class="fh-quota__fill {{ $fillClass }}" style="width: {{ $limit > 0 ? $pct : 3 }}%"></div>
    </div>
</div>
