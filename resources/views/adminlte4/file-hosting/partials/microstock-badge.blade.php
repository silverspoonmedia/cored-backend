@php
    // Microstock per-file metadata badge. Rendered only in a Microstock workspace
    // context (caller passes $show = $isMicrostock). $microstock is the
    // HostedFileResource `microstock` payload (null = no metadata row yet).
    $show = $show ?? false;
    $microstock = $microstock ?? null;
@endphp
@if ($show)
    @php
        $status = is_array($microstock) ? ($microstock['status'] ?? null) : null;
        $stages = is_array($microstock) ? ($microstock['stages'] ?? []) : [];
        $phase = is_array($microstock) ? ($microstock['phase'] ?? null) : null;
        $vision = $stages['vision'] ?? 'pending';
        $thinking = $stages['thinking'] ?? 'pending';
        $label = __('web/file-hosting.microstock.no_metadata');
        $cls = 'text-bg-secondary';

        if ($status === 'finalized') {
            $label = __('web/file-hosting.microstock.status_finalized');
            $cls = 'text-bg-success';
        } elseif ($status === 'generated') {
            $label = __('web/file-hosting.microstock.status_generated');
            $cls = 'text-bg-info';
        } elseif ($status === 'failed') {
            $label = __('web/file-hosting.microstock.status_failed_item');
            $cls = 'text-bg-danger';
        } elseif ($status === 'pending') {
            if ($phase === 'degraded') {
                $label = __('web/file-hosting.microstock.stage_degraded');
                $cls = 'text-bg-warning';
            } elseif ($vision === 'running') {
                $label = __('web/file-hosting.microstock.stage_vision');
                $cls = 'text-bg-warning';
            } elseif ($vision === 'ok' && $thinking === 'running') {
                $label = __('web/file-hosting.microstock.stage_thinking');
                $cls = 'text-bg-warning';
            } elseif ($vision === 'ok' && in_array($thinking, ['pending', 'skipped'], true)) {
                $label = __('web/file-hosting.microstock.stage_queued_thinking');
                $cls = 'text-bg-warning';
            } else {
                $label = __('web/file-hosting.microstock.status_pending');
                $cls = 'text-bg-warning';
            }
        }
    @endphp
    <span class="badge {{ $cls }} fh-ms-badge" data-fh-ms-status>{{ $label }}</span>
@endif
