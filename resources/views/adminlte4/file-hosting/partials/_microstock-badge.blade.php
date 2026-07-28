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
        $label = __('web/file-hosting.microstock.no_metadata');
        $cls = 'text-bg-secondary';
        if ($status === 'finalized') {
            $label = __('web/file-hosting.microstock.status_finalized');
            $cls = 'text-bg-success';
        } elseif ($status === 'generated') {
            $label = __('web/file-hosting.microstock.status_generated');
            $cls = 'text-bg-info';
        } elseif ($status === 'pending') {
            $label = __('web/file-hosting.microstock.status_pending');
            $cls = 'text-bg-warning';
        }
    @endphp
    <span class="badge {{ $cls }} fh-ms-badge" data-fh-ms-status>{{ $label }}</span>
@endif
