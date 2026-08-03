@props(['bare' => false])
{{-- Shared scripts: vendor JS stack + theme/scrollbar + infra modules are bundled
     via @vite('resources/js/app.js'). $bare is retained for API compatibility with
     the auth layout but no longer affects script output (sidebar wiring is
     feature-detected in theme-scroll.js). --}}
@php
    $monolithApiMessages = [
        'requestFailed' => __('ui.ajax.request_failed'),
        'serverUnreachable' => __('ui.ajax.server_unreachable'),
        'csrfExpired' => __('web/session.csrf_expired'),
        'datatableLoadFailed' => __('ui.ajax.datatable_load_failed'),
    ];
    $monolithSwalLabels = [
        'ok' => __('ui.swal.ok'),
        'confirm' => __('ui.swal.confirm'),
        'cancel' => __('ui.swal.cancel'),
    ];
@endphp
<script type="application/json" data-monolith-api-messages>@json($monolithApiMessages)</script>
<script type="application/json" data-monolith-swal-labels>@json($monolithSwalLabels)</script>
@vite('resources/js/app.js')
