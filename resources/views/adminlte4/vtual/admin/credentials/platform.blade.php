@php
    $platformName = $platform['name'] ?? $platformId;
    $platformKey = $platform['key'] ?? '';
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.credentials.title').' — '.$platformName">
    <x-adminlte4::card :title="__('web/vtual.credentials.list').': '.$platformName">
        <p class="text-muted small mb-3">
            <code>{{ $platformKey }}</code>
            — {{ __('web/vtual.credentials.subtitle') }}
        </p>

        <a href="{{ route('vtual.admin.credentials.index') }}" class="btn btn-outline-secondary btn-sm mb-3">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('web/vtual.credentials.back_platforms') }}
        </a>

        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle mb-0" aria-label="{{ __('web/vtual.table.caption_credentials') }}">
                <caption class="visually-hidden">{{ __('web/vtual.table.caption_credentials') }}</caption>
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.credentials.label') }}</th>
                        <th>{{ __('web/vtual.credentials.type') }}</th>
                        <th>{{ __('web/vtual.status') }}</th>
                        <th>{{ __('web/vtual.credentials.priority') }}</th>
                        <th>{{ __('web/vtual.credentials.hint') }}</th>
                        <th>{{ __('web/vtual.credentials.verified_at') }}</th>
                        <th>{{ __('web/vtual.credentials.runtime_expires') }}</th>
                        <th>{{ __('web/vtual.credentials.auth_failures') }}</th>
                        <th>{{ __('web/vtual.credentials.failover_skip') }}</th>
                        <th class="text-end">{{ __('web/vtual.table.label_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($credentials as $credential)
                        @php
                            $status = $credential['status'] ?? '';
                        @endphp
                        <tr>
                            <td>{{ $credential['label'] ?? '' }}</td>
                            <td><code>{{ $credential['type'] ?? '' }}</code></td>
                            <td>
                                <x-adminlte4::vtual.status-badge :status="$status" :label="__('web/vtual.credentials.status_'.$status)" />
                            </td>
                            <td>{{ $credential['priority'] ?? '' }}</td>
                            <td><code>{{ $credential['secret_hint'] ?? '—' }}</code></td>
                            <td class="small text-muted">{{ $credential['verified_at'] ?? '—' }}</td>
                            <td class="small text-muted">
                                @if (! empty($credential['has_runtime']) && ! empty($credential['runtime_expires_at']))
                                    {{ $credential['runtime_expires_at'] }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small text-muted">{{ $credential['consecutive_auth_failures'] ?? 0 }}</td>
                            <td class="small text-muted">{{ $credential['failover_skip_until'] ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <div class="btn-group" role="group" aria-label="{{ __('web/vtual.table.label_actions') }}">
                                    @if (in_array($status, ['pending', 'active'], true))
                                        <x-adminlte4::form.ajax :action="route('vtual.admin.credentials.test', $credential['id'])" method="POST" class="d-inline">
                                            <input type="hidden" name="platform_id" value="{{ $platformId }}">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                {{ __('web/vtual.credentials.test') }}
                                            </button>
                                        </x-adminlte4::form.ajax>
                                    @endif
                                    <x-adminlte4::form.ajax :action="route('vtual.admin.credentials.disable', $credential['id'])" method="POST" class="d-inline">
                                        <input type="hidden" name="platform_id" value="{{ $platformId }}">
                                        <button type="button"
                                                class="btn btn-outline-warning btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#vtualConfirmModal"
                                                data-confirm-title="{{ __('web/vtual.confirm.disable_credential_title') }}"
                                                data-confirm-body="{{ __('web/vtual.confirm.disable_credential_body') }}"
                                                data-confirm-submit-label="{{ __('web/vtual.credentials.disable') }}"
                                                data-confirm-submit-class="btn-warning">
                                            {{ __('web/vtual.credentials.disable') }}
                                        </button>
                                    </x-adminlte4::form.ajax>
                                    <x-adminlte4::form.ajax :action="route('vtual.admin.credentials.destroy', $credential['id'])" method="DELETE" class="d-inline">
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
                            </td>
                        </tr>
                        <tr>
                            <td colspan="10" class="bg-body-tertiary">
                                <x-adminlte4::form.ajax :action="route('vtual.admin.credentials.update', $credential['id'])" method="PATCH" class="row g-2 align-items-end p-2">
                                    <input type="hidden" name="platform_id" value="{{ $platformId }}">
                                    <input type="hidden" name="type" value="{{ $credential['type'] ?? '' }}">
                                    <div class="col-md-2" data-form-field="label">
                                        <label class="form-label small" for="cred_label_{{ $credential['id'] }}">{{ __('web/vtual.credentials.label') }}</label>
                                        <input type="text" id="cred_label_{{ $credential['id'] }}" name="label" class="form-control form-control-sm"
                                               value="{{ $credential['label'] ?? '' }}" required maxlength="120">
                                        <x-adminlte4::form.error name="label" />
                                    </div>
                                    <div class="col-md-1" data-form-field="priority">
                                        <label class="form-label small" for="cred_priority_{{ $credential['id'] }}">{{ __('web/vtual.credentials.priority') }}</label>
                                        <input type="number" id="cred_priority_{{ $credential['id'] }}" name="priority" class="form-control form-control-sm"
                                               value="{{ $credential['priority'] ?? 100 }}" min="0">
                                        <x-adminlte4::form.error name="priority" />
                                    </div>
                                    <div class="col-md-2" data-form-field="status">
                                        <label class="form-label small" for="cred_status_{{ $credential['id'] }}">{{ __('web/vtual.status') }}</label>
                                        <select id="cred_status_{{ $credential['id'] }}" name="status" class="form-select form-select-sm">
                                            @if ($status === 'active')
                                                <option value="active" selected>
                                                    {{ __('web/vtual.credentials.status_active') }}
                                                    ({{ __('web/vtual.credentials.status_active_via_test') }})
                                                </option>
                                            @endif
                                            @foreach (['pending', 'disabled', 'revoked'] as $statusOption)
                                                <option value="{{ $statusOption }}" @selected($status === $statusOption)>
                                                    {{ __('web/vtual.credentials.status_'.$statusOption) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-adminlte4::form.error name="status" />
                                    </div>
                                    @if (($credential['type'] ?? '') === 'api_key')
                                        <div class="col-md-3" data-form-field="api_key">
                                            <label class="form-label small" for="cred_api_key_{{ $credential['id'] }}">{{ __('web/vtual.credentials.api_key') }}</label>
                                            <input type="password" id="cred_api_key_{{ $credential['id'] }}" name="api_key" class="form-control form-control-sm"
                                                   autocomplete="off" placeholder="•">
                                            <x-adminlte4::form.error name="api_key" />
                                        </div>
                                    @elseif (($credential['type'] ?? '') === 'oauth_app')
                                        <div class="col-md-2" data-form-field="client_id">
                                            <label class="form-label small" for="cred_client_id_{{ $credential['id'] }}">{{ __('web/vtual.credentials.client_id') }}</label>
                                            <input type="text" id="cred_client_id_{{ $credential['id'] }}" name="client_id" class="form-control form-control-sm"
                                                   autocomplete="off">
                                            <x-adminlte4::form.error name="client_id" />
                                        </div>
                                        <div class="col-md-2" data-form-field="client_secret">
                                            <label class="form-label small" for="cred_client_secret_{{ $credential['id'] }}">{{ __('web/vtual.credentials.client_secret') }}</label>
                                            <input type="password" id="cred_client_secret_{{ $credential['id'] }}" name="client_secret" class="form-control form-control-sm"
                                                   autocomplete="off" placeholder="•">
                                            <x-adminlte4::form.error name="client_secret" />
                                        </div>
                                    @elseif (($credential['type'] ?? '') === 'bearer')
                                        <div class="col-md-2" data-form-field="access_token">
                                            <label class="form-label small" for="cred_access_token_{{ $credential['id'] }}">{{ __('web/vtual.credentials.access_token') }}</label>
                                            <input type="password" id="cred_access_token_{{ $credential['id'] }}" name="access_token" class="form-control form-control-sm"
                                                   autocomplete="off" placeholder="•">
                                            <x-adminlte4::form.error name="access_token" />
                                        </div>
                                        <div class="col-md-2" data-form-field="refresh_token">
                                            <label class="form-label small" for="cred_refresh_token_{{ $credential['id'] }}">{{ __('web/vtual.credentials.refresh_token') }}</label>
                                            <input type="password" id="cred_refresh_token_{{ $credential['id'] }}" name="refresh_token" class="form-control form-control-sm"
                                                   autocomplete="off">
                                            <x-adminlte4::form.error name="refresh_token" />
                                        </div>
                                    @endif
                                    <div class="col-md-2">
                                        <x-adminlte4::form.submit :label="__('web/vtual.credentials.update')" class="btn btn-primary btn-sm w-100" />
                                    </div>
                                    <div class="col-12">
                                        <span class="small text-muted">{{ __('web/vtual.credentials.secret_leave_blank') }}</span>
                                    </div>
                                </x-adminlte4::form.ajax>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <x-adminlte4::empty-state icon="bi-key" :title="__('web/vtual.credentials.empty')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-2">{{ __('web/vtual.credentials.add') }}</h5>
        <p class="small text-muted">{{ __('web/vtual.credentials.create_pending_note') }}</p>
        <x-adminlte4::form.ajax :action="route('vtual.admin.credentials.store', $platformId)" method="POST" class="border rounded p-3" id="vtual-credential-create-form">
            <div class="row g-2">
                <div class="col-md-3" data-form-field="label">
                    <label class="form-label" for="create_label">{{ __('web/vtual.credentials.label') }}</label>
                    <input type="text" id="create_label" name="label" class="form-control form-control-sm" required maxlength="120"
                           value="{{ old('label') }}">
                    <x-adminlte4::form.error name="label" />
                </div>
                <div class="col-md-3" data-form-field="type">
                    <label class="form-label" for="credential-type">{{ __('web/vtual.credentials.type') }}</label>
                    <select name="type" id="credential-type" class="form-select form-select-sm" required>
                        <option value="api_key">{{ __('web/vtual.credentials.type_api_key') }}</option>
                        <option value="oauth_app">{{ __('web/vtual.credentials.type_oauth_app') }}</option>
                        <option value="bearer">{{ __('web/vtual.credentials.type_bearer') }}</option>
                    </select>
                    <x-adminlte4::form.error name="type" />
                </div>
                <div class="col-md-2" data-form-field="priority">
                    <label class="form-label" for="create_priority">{{ __('web/vtual.credentials.priority') }}</label>
                    <input type="number" id="create_priority" name="priority" class="form-control form-control-sm" value="100" min="0">
                    <x-adminlte4::form.error name="priority" />
                </div>
            </div>

            <p class="small text-muted mt-3 mb-2">{{ __('web/vtual.credentials.secret_fields') }}</p>
            <div class="row g-2" data-secret-fields="api_key">
                <div class="col-md-6">
                    <label class="form-label" for="create_api_key">{{ __('web/vtual.credentials.api_key') }}</label>
                    <input type="password" id="create_api_key" name="api_key" class="form-control form-control-sm" autocomplete="off">
                </div>
            </div>
            <div class="row g-2 d-none" data-secret-fields="oauth_app">
                <div class="col-md-4">
                    <label class="form-label" for="create_client_id">{{ __('web/vtual.credentials.client_id') }}</label>
                    <input type="text" id="create_client_id" name="client_id" class="form-control form-control-sm" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="create_client_secret">{{ __('web/vtual.credentials.client_secret') }}</label>
                    <input type="password" id="create_client_secret" name="client_secret" class="form-control form-control-sm" autocomplete="off">
                </div>
            </div>
            <div class="row g-2 d-none" data-secret-fields="bearer">
                <div class="col-md-4">
                    <label class="form-label" for="create_access_token">{{ __('web/vtual.credentials.access_token') }}</label>
                    <input type="password" id="create_access_token" name="access_token" class="form-control form-control-sm" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="create_refresh_token">{{ __('web/vtual.credentials.refresh_token') }}</label>
                    <input type="password" id="create_refresh_token" name="refresh_token" class="form-control form-control-sm" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="create_expires_at">{{ __('web/vtual.credentials.expires_at') }}</label>
                    <input type="text" id="create_expires_at" name="expires_at" class="form-control form-control-sm" autocomplete="off">
                </div>
            </div>

            <x-adminlte4::form.submit :label="__('web/vtual.credentials.save')" class="btn btn-primary btn-sm mt-3" />
        </x-adminlte4::form.ajax>
    </x-adminlte4::card>

    @include('adminlte4.vtual.partials._confirm-modal')

    <x-slot:scripts>
        @vite(['resources/js/vtual/confirm-modal.js', 'resources/js/vtual/credential-form.js'])
    </x-slot:scripts>
</x-adminlte4::layouts.app>
