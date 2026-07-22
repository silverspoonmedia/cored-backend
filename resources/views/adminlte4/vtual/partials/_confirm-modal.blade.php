{{--
    Shared vTual confirmation modal.

    Include once per page (bottom of card). Trigger buttons carry:
      data-bs-toggle="modal" data-bs-target="#vtualConfirmModal"
      data-confirm-title="..."   (optional override)
      data-confirm-body="..."    (required)
      data-confirm-submit-label="..." (optional)
      data-confirm-submit-class="btn-danger" (optional, default btn-danger)

    The trigger must live inside a <x-adminlte4::form.ajax> wrapper. On confirm,
    confirm-modal.js calls activeForm.requestSubmit() so the AJAX form handler
    runs normally (validation, spinner, redirect).
--}}
<div class="modal fade" id="vtualConfirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="vtualConfirmLabel">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vtualConfirmLabel" data-confirm-title>{{ __('web/vtual.confirm.default_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.actions.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <p data-confirm-body class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.actions.cancel') }}</button>
                <button type="button" class="btn btn-danger" data-confirm-submit>
                    <span class="spinner-border spinner-border-sm d-none" data-form-submit-spinner role="status" aria-hidden="true"></span>
                    <span data-confirm-submit-label>{{ __('web/vtual.confirm.default_submit') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
