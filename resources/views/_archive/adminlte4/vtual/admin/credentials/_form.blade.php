@php
    $isEdit = ! empty($credential);
    $credentialType = $credential['type'] ?? old('type', 'api_key');
    $credentialStatus = $credential['status'] ?? 'pending';
    $formId = $isEdit ? 'vtual-credential-edit-form' : 'vtual-credential-create-form';
@endphp

@if ($isEdit)
    <input type="hidden" name="platform_id" value="{{ $platformId }}">
    <input type="hidden" name="type" value="{{ $credentialType }}">
@endif

<div class="row g-3">
    <div class="col-md-4" data-form-field="label">
        <label class="form-label" for="cred_label">{{ __('web/vtual.credentials.label') }}</label>
        <input type="text" id="cred_label" name="label" class="form-control form-control-sm" required maxlength="120"
               value="{{ old('label', $credential['label'] ?? '') }}">
        <x-adminlte4::form.error name="label" />
    </div>

    @if (! $isEdit)
        <div class="col-md-4" data-form-field="type">
            <label class="form-label" for="credential-type">{{ __('web/vtual.credentials.type') }}</label>
            <select name="type" id="credential-type" class="form-select form-select-sm" required>
                <option value="api_key" @selected($credentialType === 'api_key')>{{ __('web/vtual.credentials.type_api_key') }}</option>
                <option value="oauth_app" @selected($credentialType === 'oauth_app')>{{ __('web/vtual.credentials.type_oauth_app') }}</option>
                <option value="bearer" @selected($credentialType === 'bearer')>{{ __('web/vtual.credentials.type_bearer') }}</option>
            </select>
            <x-adminlte4::form.error name="type" />
        </div>
    @else
        <div class="col-md-4">
            <label class="form-label">{{ __('web/vtual.credentials.type') }}</label>
            <div class="form-control form-control-sm bg-body-secondary"><code>{{ $credentialType }}</code></div>
        </div>
    @endif

    <div class="col-md-2" data-form-field="priority">
        <label class="form-label" for="cred_priority">{{ __('web/vtual.credentials.priority') }}</label>
        <input type="number" id="cred_priority" name="priority" class="form-control form-control-sm"
               value="{{ old('priority', $credential['priority'] ?? 100) }}" min="0">
        <x-adminlte4::form.error name="priority" />
    </div>

    @if ($isEdit)
        <div class="col-md-2" data-form-field="status">
            <label class="form-label" for="cred_status">{{ __('web/vtual.status') }}</label>
            <select id="cred_status" name="status" class="form-select form-select-sm">
                @if ($credentialStatus === 'active')
                    <option value="active" selected>
                        {{ __('web/vtual.credentials.status_active') }}
                        ({{ __('web/vtual.credentials.status_active_via_test') }})
                    </option>
                @endif
                @foreach (['pending', 'disabled', 'revoked'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected($credentialStatus === $statusOption)>
                        {{ __('web/vtual.credentials.status_'.$statusOption) }}
                    </option>
                @endforeach
            </select>
            <x-adminlte4::form.error name="status" />
        </div>
    @endif
</div>

<p class="small text-muted mt-3 mb-2">{{ __('web/vtual.credentials.secret_fields') }}</p>
@if ($isEdit)
    <p class="small text-muted">{{ __('web/vtual.credentials.secret_leave_blank') }}</p>
@else
    <p class="small text-muted">{{ __('web/vtual.credentials.create_pending_note') }}</p>
@endif

<div class="row g-3" data-secret-fields="api_key" @class(['d-none' => $credentialType !== 'api_key'])>
    <div class="col-md-6" data-form-field="api_key">
        <label class="form-label" for="cred_api_key">{{ __('web/vtual.credentials.api_key') }}</label>
        <input type="password" id="cred_api_key" name="api_key" class="form-control form-control-sm" autocomplete="off"
               @disabled($credentialType !== 'api_key')>
        <x-adminlte4::form.error name="api_key" />
    </div>
</div>

<div class="row g-3" data-secret-fields="oauth_app" @class(['d-none' => $credentialType !== 'oauth_app'])>
    <div class="col-md-4" data-form-field="client_id">
        <label class="form-label" for="cred_client_id">{{ __('web/vtual.credentials.client_id') }}</label>
        <input type="text" id="cred_client_id" name="client_id" class="form-control form-control-sm" autocomplete="off"
               @disabled($credentialType !== 'oauth_app')>
        <x-adminlte4::form.error name="client_id" />
    </div>
    <div class="col-md-4" data-form-field="client_secret">
        <label class="form-label" for="cred_client_secret">{{ __('web/vtual.credentials.client_secret') }}</label>
        <input type="password" id="cred_client_secret" name="client_secret" class="form-control form-control-sm" autocomplete="off"
               @disabled($credentialType !== 'oauth_app')>
        <x-adminlte4::form.error name="client_secret" />
    </div>
</div>

<div class="row g-3" data-secret-fields="bearer" @class(['d-none' => $credentialType !== 'bearer'])>
    <div class="col-md-4" data-form-field="access_token">
        <label class="form-label" for="cred_access_token">{{ __('web/vtual.credentials.access_token') }}</label>
        <input type="password" id="cred_access_token" name="access_token" class="form-control form-control-sm" autocomplete="off"
               @disabled($credentialType !== 'bearer')>
        <x-adminlte4::form.error name="access_token" />
    </div>
    <div class="col-md-4" data-form-field="refresh_token">
        <label class="form-label" for="cred_refresh_token">{{ __('web/vtual.credentials.refresh_token') }}</label>
        <input type="password" id="cred_refresh_token" name="refresh_token" class="form-control form-control-sm" autocomplete="off"
               @disabled($credentialType !== 'bearer')>
        <x-adminlte4::form.error name="refresh_token" />
    </div>
    <div class="col-md-4" data-form-field="expires_at">
        <label class="form-label" for="cred_expires_at">{{ __('web/vtual.credentials.expires_at') }}</label>
        <input type="text" id="cred_expires_at" name="expires_at" class="form-control form-control-sm" autocomplete="off"
               @disabled($credentialType !== 'bearer')>
        <x-adminlte4::form.error name="expires_at" />
    </div>
</div>

<div class="mt-3">
    <x-adminlte4::form.submit :label="$isEdit ? __('web/vtual.credentials.update') : __('web/vtual.credentials.save')" class="btn btn-primary btn-sm" />
</div>
