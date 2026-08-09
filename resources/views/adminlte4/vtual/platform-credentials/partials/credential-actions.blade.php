@php
    $status = $credential['status'] ?? '';
    $credentialId = $credential['id'] ?? '';
@endphp
<div class="vtual-credential-actions d-flex flex-wrap justify-content-end align-items-center gap-1" role="group" aria-label="{{ __('web/vtual.table.label_actions') }}">
    <a href="{{ route('vtual.platform-credentials.edit', $credentialId) }}" class="btn btn-outline-secondary btn-sm">
        {{ __('web/vtual.admin.credentials.edit_action') }}
    </a>
    @if (in_array($status, ['pending', 'active'], true))
        <div data-credential-test-form>
            <x-adminlte4::form.ajax
                :action="route('vtual.platform-credentials.test', $credentialId)"
                method="POST"
                :showAlert="false"
                class="vtual-credential-actions__form"
            >
                <input type="hidden" name="platform_id" value="{{ $platformId }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    {{ __('web/vtual.credentials.test') }}
                </button>
            </x-adminlte4::form.ajax>
        </div>
    @endif
    <x-adminlte4::form.ajax
        :action="route('vtual.platform-credentials.revoke', $credentialId)"
        method="POST"
        :showAlert="false"
        class="vtual-credential-actions__form @class(['d-none' => $status !== 'active'])"
        data-credential-revoke-form
    >
        <input type="hidden" name="platform_id" value="{{ $platformId }}">
        <button type="button"
                class="btn btn-outline-warning btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#vtualConfirmModal"
                data-confirm-title="{{ __('web/vtual.confirm.revoke_credential_title') }}"
                data-confirm-body="{{ __('web/vtual.confirm.revoke_credential_body') }}"
                data-confirm-submit-label="{{ __('web/vtual.credentials.revoke') }}"
                data-confirm-submit-class="btn-warning">
            {{ __('web/vtual.credentials.revoke') }}
        </button>
    </x-adminlte4::form.ajax>
    <x-adminlte4::form.ajax
        :action="route('vtual.platform-credentials.destroy', $credentialId)"
        method="DELETE"
        :showAlert="false"
        class="vtual-credential-actions__form @class(['d-none' => $status === 'active'])"
        data-credential-delete-form
    >
        <input type="hidden" name="platform_id" value="{{ $platformId }}">
        <button type="button"
                class="btn btn-outline-danger btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#vtualConfirmModal"
                data-confirm-title="{{ __('web/vtual.confirm.delete_credential_title') }}"
                data-confirm-body="{{ __('web/vtual.confirm.delete_credential_body') }}"
                data-confirm-submit-label="{{ __('web/vtual.credentials.delete') }}">
            <i class="bi bi-trash" aria-hidden="true"></i>
        </button>
    </x-adminlte4::form.ajax>
</div>
