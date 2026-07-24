@php
    $folders = collect($trash['folders'] ?? []);
    $files = collect($trash['files'] ?? []);
    $workspaceId = $workspaceId ?? null;
    $ownerQuery = array_filter([
        'user_id' => $ownerUserId,
        'workspace_id' => $workspaceId,
    ]);
    $fhRealtime = app(\App\Domain\FileHosting\Support\FileHostingRealtime::class);
    $fhBroadcast = $fhRealtime->clientConfig();
    $trashConfig = [
        'ownerUserId' => $ownerUserId,
        'workspaceId' => $workspaceId,
        'browseUrl' => route('file-hosting.index', $ownerQuery),
        'trashUrl' => route('file-hosting.trash', $ownerQuery),
        'realtime' => [
            'enabled' => $fhBroadcast !== null,
            'broadcast' => $fhBroadcast,
            'userId' => auth('api')->id(),
            'page' => 'trash',
        ],
        'messages' => [
            'success' => __('web/file-hosting.ajax_success'),
            'error' => __('web/file-hosting.ajax_error'),
            'networkError' => __('web/file-hosting.network_error'),
        ],
    ];
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.trash_title')">
    <div class="fh-manager" data-file-manager data-config="{{ json_encode($trashConfig) }}">
        <x-adminlte4::card :title="__('web/file-hosting.trash_title')">
            <x-slot:actions>
                <a href="{{ route('file-hosting.index', $ownerQuery) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> {{ __('web/file-hosting.back') }}
                </a>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="table fh-table">
                    <thead>
                        <tr>
                            <th class="fh-table__icon-cell"></th>
                            <th>{{ __('web/file-hosting.columns.name') }}</th>
                            <th>{{ __('web/file-hosting.columns.type') }}</th>
                            <th>{{ __('web/file-hosting.columns.deleted_at') }}</th>
                            <th class="text-end">{{ __('web/file-hosting.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($folders as $folder)
                            <tr data-fh-row data-fh-entity="folder" data-fh-id="{{ $folder['id'] }}">
                        <td class="fh-table__icon-cell">
                            @include('adminlte4.file-hosting.partials._file-icon', ['mime' => null, 'isFolder' => true])
                        </td>
                        <td>
                            <span class="fh-table__name">
                                <span class="fh-table__name-text">{{ $folder['name'] ?? '' }}</span>
                            </span>
                        </td>
                        <td class="fh-table__type">{{ __('web/file-hosting.type_folder') }}</td>
                        <td class="fh-table__size">{{ $folder['deleted_at'] ?? '' }}</td>
                        <td class="fh-table__actions">
                            @can('edit file hosting folder')
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.restore') }}"
                                        data-fh-ajax-action="restore-folder"
                                        data-fh-action-url="{{ route('file-hosting.folders.restore', $folder['id']) }}"
                                        data-fh-method="POST"
                                        data-fh-confirm="{{ __('web/file-hosting.confirm_restore') }}"
                                        data-fh-redirect="{{ route('file-hosting.trash', $ownerQuery) }}">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            @endcan
                            @can('delete file hosting folder')
                                <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.force_delete') }}"
                                        data-fh-ajax-action="force-delete-folder"
                                        data-fh-action-url="{{ route('file-hosting.folders.force-destroy', $folder['id']) }}"
                                        data-fh-method="DELETE"
                                        data-fh-confirm="{{ __('web/file-hosting.confirm_force_delete') }}"
                                        data-fh-redirect="{{ route('file-hosting.trash', $ownerQuery) }}">
                                    <i class="bi bi-x-octagon"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach

                        @foreach ($files as $file)
                            <tr data-fh-row data-fh-entity="file" data-fh-id="{{ $file['id'] }}">
                        <td class="fh-table__icon-cell">
                            @include('adminlte4.file-hosting.partials._file-icon', ['mime' => $file['mime'] ?? null, 'isFolder' => false])
                        </td>
                        <td>
                            <span class="fh-table__name">
                                <span class="fh-table__name-text">{{ $file['name'] ?? '' }}</span>
                            </span>
                        </td>
                        <td class="fh-table__type">{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</td>
                        <td class="fh-table__size">{{ $file['deleted_at'] ?? '' }}</td>
                        <td class="fh-table__actions">
                            @can('edit file hosting file')
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.restore') }}"
                                        data-fh-ajax-action="restore-file"
                                        data-fh-action-url="{{ route('file-hosting.files.restore', $file['id']) }}"
                                        data-fh-method="POST"
                                        data-fh-confirm="{{ __('web/file-hosting.confirm_restore') }}"
                                        data-fh-redirect="{{ route('file-hosting.trash', $ownerQuery) }}">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            @endcan
                            @can('delete file hosting file')
                                <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.force_delete') }}"
                                        data-fh-ajax-action="force-delete-file"
                                        data-fh-action-url="{{ route('file-hosting.files.force-destroy', $file['id']) }}"
                                        data-fh-method="DELETE"
                                        data-fh-confirm="{{ __('web/file-hosting.confirm_force_delete') }}"
                                        data-fh-redirect="{{ route('file-hosting.trash', $ownerQuery) }}">
                                    <i class="bi bi-x-octagon"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach

                        @if ($folders->isEmpty() && $files->isEmpty())
                            <tr>
                                <td colspan="5">
                                    <div class="fh-empty">
                                        <i class="bi bi-trash3 fh-empty__icon"></i>
                                        <div class="fh-empty__text">{{ __('web/file-hosting.trash_empty') }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-adminlte4::card>
    </div>

    @vite(['resources/css/file-hosting/file-hosting.css', 'resources/js/file-hosting/file-manager.js'])
</x-adminlte4::layouts.app>
