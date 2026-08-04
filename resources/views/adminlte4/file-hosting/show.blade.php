@php
    $ownerQuery = array_filter(['user_id' => $ownerUserId]);
    $folderId = $file['folder_id'] ?? null;
    $backQuery = array_filter(array_merge($ownerQuery, [
        'folder_id' => $folderId,
        'workspace_id' => $file['workspace_id'] ?? null,
    ]));
    $fhRealtime = app(\App\Domain\FileHosting\Support\FileHostingRealtime::class);
    $fhBroadcast = $fhRealtime->clientConfig();
    $authUser = auth('api')->user();
    $fhBase = rtrim(route('file-hosting.index'), '/');
    $showFmConfig = [
        'spaShell' => true,
        'fhBase' => $fhBase,
        'ownerUserId' => $ownerUserId,
        'workspaceId' => $file['workspace_id'] ?? null,
        'currentFolderId' => $folderId,
        'browseUrl' => route('file-hosting.index', $backQuery),
        'fileBase' => $fhBase.'/files',
        'fileShowBase' => $fhBase.'/files',
        'folderBase' => $fhBase.'/folders',
        'fileRenameBase' => $fhBase.'/files',
        'folderRenameBase' => $fhBase.'/folders',
        'trashUrl' => route('file-hosting.trash', $ownerQuery),
        'quotaUrl' => route('file-hosting.quota', $ownerQuery),
        'planState' => 'paid',
        'visLink' => \App\Domain\FileHosting\Enums\Visibility::AnyoneWithLink->value,
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
            'userId' => auth('api')->id(),
            'page' => 'show',
            'fileId' => $file['id'] ?? null,
        ],
        'messages' => [
            'searchNoResults' => __('web/file-hosting.search_no_results'),
            'rootLabel' => __('web/file-hosting.root_label'),
            'loadFailed' => __('web/file-hosting.load_failed'),
            'linkCopied' => __('web/file-hosting.link_copied'),
            'success' => __('web/file-hosting.ajax_success'),
            'error' => __('web/file-hosting.ajax_error'),
            'networkError' => __('web/file-hosting.network_error'),
            'actions' => [
                'delete' => __('web/file-hosting.delete'),
                'rename' => __('web/file-hosting.rename'),
                'share' => __('web/file-hosting.share'),
                'confirmDeleteFile' => __('web/file-hosting.confirm_delete_file'),
            ],
            'visibility' => [
                'private' => __('web/file-hosting.visibility.private'),
                'anyone_with_link' => __('web/file-hosting.visibility.anyone_with_link'),
            ],
        ],
        'showLabels' => [
            'name' => __('web/file-hosting.columns.name'),
            'type' => __('web/file-hosting.columns.type'),
            'size' => __('web/file-hosting.columns.size'),
            'visibility' => __('web/file-hosting.columns.visibility'),
            'views' => __('web/file-hosting.stats.views'),
            'downloads' => __('web/file-hosting.stats.downloads'),
            'stats' => __('web/file-hosting.stats.views'),
            'back' => __('web/file-hosting.back'),
            'download' => __('web/file-hosting.download'),
            'save' => __('web/file-hosting.save'),
            'private' => __('web/file-hosting.visibility.private'),
            'anyone_with_link' => __('web/file-hosting.visibility.anyone_with_link'),
        ],
    ];

    $vueFhBootstrap = [
        'config' => $showFmConfig,
        'browse' => ['folders' => [], 'files' => [], 'breadcrumb' => []],
        'trash' => null,
        'showFile' => $file,
        'quota' => null,
        'workspaces' => [],
        'workspaceMembers' => [],
        'workspaceActivity' => [],
        'currentWorkspace' => null,
        'ui' => [
            'canCreateFolder' => false,
            'canCreateFile' => false,
        ],
        'upload' => null,
    ];
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.show_title')">
    <div id="vue-file-hosting"></div>
    <script type="application/json" data-vue-file-hosting data-vue-mount-failed="{{ __('web/file-hosting.vue_mount_failed') }}">@json($vueFhBootstrap)</script>

    @vite('resources/css/file-hosting/file-hosting.css')

    <x-slot:scripts>
        @vite('resources/js/vue/islands/file-hosting/main.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
