    {{-- Rename File Modal --}}
    @can('edit file hosting file')
        <div class="modal fade" id="fhRenameFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFileForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil me-1"></i>{{ __('web/file-hosting.rename') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-rename-file-name">{{ __('web/file-hosting.file_name') }}</label>
                        <input type="text" name="name" id="fh-rename-file-name" class="form-control" required maxlength="255">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save_rename') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Share File Modal --}}
        <div class="modal fade" id="fhShareFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhShareFileForm">
                    @csrf
                    @method('PATCH')
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-share me-1"></i>{{ __('web/file-hosting.share') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-share-file-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                        <select name="visibility" id="fh-share-file-vis" class="form-select mb-3">
                            <option value="{{ $visPrivate }}">{{ __('web/file-hosting.visibility.private') }}</option>
                            <option value="{{ $visLink }}">{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                        </select>
                        <div id="fh-share-file-link-wrap" class="d-none">
                            <div class="fh-share-box">
                                <div class="fh-share-box__label">{{ __('web/file-hosting.share_link') }}</div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="fh-share-file-link" readonly>
                                    <button type="button" class="btn btn-outline-primary" data-fh-copy="fh-share-file-link">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text mt-2">{{ __('web/file-hosting.visibility.hint_link') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include(config('web.layout').'.file-hosting.partials.move-modal', [
            'modalId' => 'fhMoveFileModal',
            'formId' => 'fhMoveFileForm',
            'title' => __('web/file-hosting.move_title'),
            'ownerUserId' => $ownerUserId,
        ])
    @endcan

    {{-- Rename / Share / Move Folder Modals --}}
    @can('edit file hosting folder')
        <div class="modal fade" id="fhRenameFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFolderForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil me-1"></i>{{ __('web/file-hosting.rename') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-rename-folder-name">{{ __('web/file-hosting.folder_name') }}</label>
                        <input type="text" name="name" id="fh-rename-folder-name" class="form-control" required maxlength="255">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save_rename') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="fhShareFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhShareFolderForm">
                    @csrf
                    @method('PATCH')
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-share me-1"></i>{{ __('web/file-hosting.share') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-share-folder-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                        <select name="visibility" id="fh-share-folder-vis" class="form-select mb-3">
                            <option value="{{ $visPrivate }}">{{ __('web/file-hosting.visibility.private') }}</option>
                            <option value="{{ $visLink }}">{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                        </select>
                        <div id="fh-share-folder-link-wrap" class="d-none">
                            <div class="fh-share-box">
                                <div class="fh-share-box__label">{{ __('web/file-hosting.share_link') }}</div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="fh-share-folder-link" readonly>
                                    <button type="button" class="btn btn-outline-primary" data-fh-copy="fh-share-folder-link">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text mt-2">{{ __('web/file-hosting.visibility.hint_folder') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include(config('web.layout').'.file-hosting.partials.move-modal', [
            'modalId' => 'fhMoveFolderModal',
            'formId' => 'fhMoveFolderForm',
            'title' => __('web/file-hosting.move_title'),
            'ownerUserId' => $ownerUserId,
        ])
    @endcan