@php
    $workspaceId = $workspaceId ?? null;
    $ownerQuery = array_filter([
        'user_id' => $ownerUserId,
        'workspace_id' => $workspaceId,
    ]);
    $fhRealtime = app(\App\Domain\FileHosting\Support\FileHostingRealtime::class);
    $fhBroadcast = $fhRealtime->clientConfig();
    $authUser = auth('api')->user();
    $fhBase = rtrim(route('file-hosting.index'), '/');
    $trashConfig = [
        'spaShell' => true,
        'fhBase' => $fhBase,
        'ownerUserId' => $ownerUserId,
        'workspaceId' => $workspaceId,
        'browseUrl' => route('file-hosting.index', $ownerQuery),
        'trashUrl' => route('file-hosting.trash', $ownerQuery),
        'quotaUrl' => route('file-hosting.quota', $ownerQuery),
        'fileBase' => $fhBase.'/files',
        'folderBase' => $fhBase.'/folders',
        'fileShowBase' => $fhBase.'/files',
        'fileRenameBase' => $fhBase.'/files',
        'folderRenameBase' => $fhBase.'/folders',
        'permissions' => [
            'editFile' => $authUser?->can('edit file hosting file') ?? false,
            'deleteFile' => $authUser?->can('delete file hosting file') ?? false,
            'editFolder' => $authUser?->can('edit file hosting folder') ?? false,
            'deleteFolder' => $authUser?->can('delete file hosting folder') ?? false,
            'createFile' => false,
            'createFolder' => false,
        ],
        'realtime' => [
            'enabled' => $fhBroadcast !== null,
            'broadcast' => $fhBroadcast,
            'userId' => $authUser?->id,
            'page' => 'trash',
        ],
        'messages' => [
            'success' => __('web/file-hosting.ajax_success'),
            'error' => __('web/file-hosting.ajax_error'),
            'networkError' => __('web/file-hosting.network_error'),
            'loadFailed' => __('web/file-hosting.load_failed'),
            'typeFolder' => __('web/file-hosting.type_folder'),
            'typeFile' => __('web/file-hosting.type_file'),
            'restore' => __('web/file-hosting.restore'),
            'forceDelete' => __('web/file-hosting.force_delete'),
            'confirmRestore' => __('web/file-hosting.confirm_restore'),
            'confirmForceDelete' => __('web/file-hosting.confirm_force_delete'),
            'trashEmpty' => __('web/file-hosting.trash_empty'),
            'rootLabel' => __('web/file-hosting.root_label'),
        ],
        'showLabels' => [
            'name' => __('web/file-hosting.columns.name'),
            'back' => __('web/file-hosting.back'),
        ],
    ];

    $vueFhBootstrap = [
        'config' => $trashConfig,
        'browse' => ['folders' => [], 'files' => [], 'breadcrumb' => []],
        'trash' => $trash,
        'showFile' => null,
        'quota' => null,
        'workspaces' => [],
        'workspaceMembers' => [],
        'workspaceActivity' => [],
        'currentWorkspace' => null,
        'ui' => [
            'canCreateFolder' => false,
            'canCreateFile' => false,
            'trash' => __('web/file-hosting.trash'),
            'columns' => [
                'name' => __('web/file-hosting.columns.name'),
                'type' => __('web/file-hosting.columns.type'),
                'deleted_at' => __('web/file-hosting.columns.deleted_at'),
                'actions' => __('web/file-hosting.columns.actions'),
            ],
        ],
        'upload' => null,
    ];
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.trash_title')">
    <div id="vue-file-hosting"></div>
    <script type="application/json" data-vue-file-hosting data-vue-mount-failed="{{ __('web/file-hosting.vue_mount_failed') }}">@json($vueFhBootstrap)</script>

    @vite('resources/css/file-hosting/file-hosting.css')

    <x-slot:scripts>
        @vite('resources/js/vue/islands/file-hosting/main.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
