@php
    /**
     * Quota progress bar.
     *
     * @var array|null $quota  ['used_bytes' => int, 'quota_bytes' => int, 'remaining_bytes' => int]
     */
    if (! $quota) {
        return;
    }

    $used = (int) ($quota['used_bytes'] ?? 0);
    $limit = (int) ($quota['quota_bytes'] ?? 0);
    $pct = $limit > 0 ? min(100, ($used / $limit) * 100) : 0;
    $fillClass = $pct >= 90 ? 'is-danger' : ($pct >= 75 ? 'is-warn' : '');

    $formatBytes = function (int $bytes): string {
        if ($bytes === 0) {
            return '0 B';
        }
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1) . ' MB';
        }
        return number_format($bytes / (1024 * 1024 * 1024), 1) . ' GB';
    };
@endphp
<div class="fh-quota">
    <div class="fh-quota__header">
        <span class="fh-quota__label">{{ __('web/file-hosting.quota_used') }}</span>
        <span class="fh-quota__value">
            {{ $formatBytes($used) }}
            @if ($limit > 0)
                / {{ $formatBytes($limit) }}
            @else
                / {{ __('web/file-hosting.quota_unlimited') }}
            @endif
        </span>
    </div>
    <div class="fh-quota__track">
        <div class="fh-quota__fill {{ $fillClass }}" style="width: {{ $limit > 0 ? $pct : 3 }}%"></div>
    </div>
</div>
