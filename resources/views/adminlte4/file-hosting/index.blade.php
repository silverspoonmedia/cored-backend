@include('adminlte4.file-hosting.partials._fm-config')

<x-adminlte4::layouts.app :title="__('web/file-hosting.title')">
    <div class="fh-manager" data-file-manager data-fh-spa data-fh-app data-config="{{ json_encode($fmConfig) }}">

        @include('adminlte4.file-hosting.partials._toolbar-zone')

        @include('adminlte4.file-hosting.partials._browse-views')

        @include('adminlte4.file-hosting.partials._trash-show-panels')

    </div>

    @include('adminlte4.file-hosting.partials._workspace-modals')

    @include('adminlte4.file-hosting.partials._microstock-review-panel')

    @include('adminlte4.file-hosting.partials._usage-offcanvas')

    @include('adminlte4.file-hosting.partials._file-folder-modals')

    @vite(['resources/css/file-hosting/file-hosting.css', 'resources/js/file-hosting/file-manager.js'])
</x-adminlte4::layouts.app>
