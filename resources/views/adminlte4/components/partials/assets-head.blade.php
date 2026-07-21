{{-- Shared <head> assets for every page (AdminLTE 4 via CDN, per the reference theme). --}}
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="color-scheme" content="light dark" />
<meta name="theme-color" content="#6366f1" media="(prefers-color-scheme: light)" />
<meta name="theme-color" content="#1e293b" media="(prefers-color-scheme: dark)" />
@if(session('datatable_reload'))
<meta name="datatable-reload" content="{{ session('datatable_reload') }}" />
@endif

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
    integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q="
    crossorigin="anonymous"
    media="print"
    onload="this.media = 'all'"
/>
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
    integrity="sha256-oivQhpZV0btjmbFEGWLf3iCksuV96ZwoW5yWFRfnmNg="
    crossorigin="anonymous"
/>
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    integrity="sha256-pdY4ejLKO67E0CM2tbPtq1DJ3VGDVVdqAR6j3ZwdiE4="
    crossorigin="anonymous"
/>
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css"
    integrity="sha256-lalK6iapuNKGhnAUWocFdzfnnhN9yt67N2kW8j8RUyw="
    crossorigin="anonymous"
/>

@vite('resources/css/monolith-overrides.css')
