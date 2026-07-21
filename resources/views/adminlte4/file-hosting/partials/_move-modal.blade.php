@php
    /**
     * Move modal — shared between file and folder move.
     *
     * @var string $modalId     e.g. 'fhMoveFileModal'
     * @var string $formId      e.g. 'fhMoveFileForm'
     * @var string $title       Modal title
     * @var string $ownerUserId Owner user ID (for query string)
     */
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <form method="POST" action="#" class="modal-content" id="{{ $formId }}">
            @csrf
            @method('PATCH')
            @if ($ownerUserId)
                <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
            @endif
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-folder-symlink me-1"></i>
                    {{ $title }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">{{ __('web/file-hosting.select_folder') }}</label>
                <div class="fh-move-tree" data-fh-move-tree>
                    <div class="text-muted text-center py-3">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                </div>
                <input type="hidden" data-fh-move-target value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-folder-symlink me-1"></i>
                    {{ __('web/file-hosting.move') }}
                </button>
            </div>
        </form>
    </div>
</div>
