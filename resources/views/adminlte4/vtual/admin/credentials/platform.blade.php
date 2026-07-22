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
            {{ __('web/vtual.credentials.back_platforms') }}
        </a>

        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.credentials.label') }}</th>
                        <th>{{ __('web/vtual.credentials.type') }}</th>
                        <th>{{ __('web/vtual.status') }}</th>
                        <th>{{ __('web/vtual.credentials.priority') }}</th>
                        <th>{{ __('web/vtual.credentials.hint') }}</th>
                        <th>{{ __('web/vtual.credentials.verified_at') }}</th>
                        <th>{{ __('web/vtual.credentials.runtime_expires') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($credentials as $credential)
                        @php
                            $status = $credential['status'] ?? '';
                            $badgeClass = match ($status) {
                                'active' => 'text-bg-success',
                                'pending' => 'text-bg-warning',
                                'disabled' => 'text-bg-secondary',
                                'revoked' => 'text-bg-dark',
                                default => 'text-bg-light border',
                            };
                        @endphp
                        <tr>
                            <td>{{ $credential['label'] ?? '' }}</td>
                            <td><code>{{ $credential['type'] ?? '' }}</code></td>
                            <td>
                                <span class="badge {{ $badgeClass }}">
                                    {{ __('web/vtual.credentials.status_'.$status) }}
                                </span>
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
                            <td class="text-end text-nowrap">
                                @if (in_array($status, ['pending', 'active'], true))
                                    <form method="POST"
                                          action="{{ route('vtual.admin.credentials.test', $credential['id']) }}"
                                          class="d-inline">
                                        @csrf
                                        <input type="hidden" name="platform_id" value="{{ $platformId }}">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            {{ __('web/vtual.credentials.test') }}
                                        </button>
                                    </form>
                                @endif
                                <form method="POST"
                                      action="{{ route('vtual.admin.credentials.disable', $credential['id']) }}"
                                      class="d-inline">
                                    @csrf
                                    <input type="hidden" name="platform_id" value="{{ $platformId }}">
                                    <button type="submit" class="btn btn-outline-warning btn-sm">
                                        {{ __('web/vtual.credentials.disable') }}
                                    </button>
                                </form>
                                <form method="POST"
                                      action="{{ route('vtual.admin.credentials.destroy', $credential['id']) }}"
                                      class="d-inline"
                                      onsubmit="return confirm(@js(__('web/vtual.credentials.confirm_delete')));">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="platform_id" value="{{ $platformId }}">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        {{ __('web/vtual.credentials.delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="8" class="bg-body-tertiary">
                                <form method="POST"
                                      action="{{ route('vtual.admin.credentials.update', $credential['id']) }}"
                                      class="row g-2 align-items-end p-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="platform_id" value="{{ $platformId }}">
                                    <input type="hidden" name="type" value="{{ $credential['type'] ?? '' }}">
                                    <div class="col-md-2">
                                        <label class="form-label small">{{ __('web/vtual.credentials.label') }}</label>
                                        <input type="text" name="label" class="form-control form-control-sm"
                                               value="{{ $credential['label'] ?? '' }}" required maxlength="120">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label small">{{ __('web/vtual.credentials.priority') }}</label>
                                        <input type="number" name="priority" class="form-control form-control-sm"
                                               value="{{ $credential['priority'] ?? 100 }}" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">{{ __('web/vtual.status') }}</label>
                                        <select name="status" class="form-select form-select-sm">
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
                                    </div>
                                    @if (($credential['type'] ?? '') === 'api_key')
                                        <div class="col-md-3">
                                            <label class="form-label small">{{ __('web/vtual.credentials.api_key') }}</label>
                                            <input type="password" name="api_key" class="form-control form-control-sm"
                                                   autocomplete="off" placeholder="••••">
                                        </div>
                                    @elseif (($credential['type'] ?? '') === 'oauth_app')
                                        <div class="col-md-2">
                                            <label class="form-label small">{{ __('web/vtual.credentials.client_id') }}</label>
                                            <input type="text" name="client_id" class="form-control form-control-sm"
                                                   autocomplete="off">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">{{ __('web/vtual.credentials.client_secret') }}</label>
                                            <input type="password" name="client_secret" class="form-control form-control-sm"
                                                   autocomplete="off" placeholder="••••">
                                        </div>
                                    @elseif (($credential['type'] ?? '') === 'bearer')
                                        <div class="col-md-2">
                                            <label class="form-label small">{{ __('web/vtual.credentials.access_token') }}</label>
                                            <input type="password" name="access_token" class="form-control form-control-sm"
                                                   autocomplete="off" placeholder="••••">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">{{ __('web/vtual.credentials.refresh_token') }}</label>
                                            <input type="password" name="refresh_token" class="form-control form-control-sm"
                                                   autocomplete="off">
                                        </div>
                                    @endif
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                            {{ __('web/vtual.credentials.update') }}
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <span class="small text-muted">{{ __('web/vtual.credentials.secret_leave_blank') }}</span>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-adminlte4::empty-state icon="bi-key" :title="__('web/vtual.credentials.empty')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h5 class="mb-2">{{ __('web/vtual.credentials.add') }}</h5>
        <p class="small text-muted">{{ __('web/vtual.credentials.create_pending_note') }}</p>
        <form method="POST" action="{{ route('vtual.admin.credentials.store', $platformId) }}"
              class="border rounded p-3" id="vtual-credential-create-form">
            @csrf
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">{{ __('web/vtual.credentials.label') }}</label>
                    <input type="text" name="label" class="form-control form-control-sm" required maxlength="120"
                           value="{{ old('label') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('web/vtual.credentials.type') }}</label>
                    <select name="type" id="credential-type" class="form-select form-select-sm" required>
                        <option value="api_key">{{ __('web/vtual.credentials.type_api_key') }}</option>
                        <option value="oauth_app">{{ __('web/vtual.credentials.type_oauth_app') }}</option>
                        <option value="bearer">{{ __('web/vtual.credentials.type_bearer') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('web/vtual.credentials.priority') }}</label>
                    <input type="number" name="priority" class="form-control form-control-sm" value="100" min="0">
                </div>
            </div>

            <p class="small text-muted mt-3 mb-2">{{ __('web/vtual.credentials.secret_fields') }}</p>
            <div class="row g-2" data-secret-fields="api_key">
                <div class="col-md-6">
                    <label class="form-label">{{ __('web/vtual.credentials.api_key') }}</label>
                    <input type="password" name="api_key" class="form-control form-control-sm" autocomplete="off">
                </div>
            </div>
            <div class="row g-2 d-none" data-secret-fields="oauth_app">
                <div class="col-md-4">
                    <label class="form-label">{{ __('web/vtual.credentials.client_id') }}</label>
                    <input type="text" name="client_id" class="form-control form-control-sm" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('web/vtual.credentials.client_secret') }}</label>
                    <input type="password" name="client_secret" class="form-control form-control-sm" autocomplete="off">
                </div>
            </div>
            <div class="row g-2 d-none" data-secret-fields="bearer">
                <div class="col-md-4">
                    <label class="form-label">{{ __('web/vtual.credentials.access_token') }}</label>
                    <input type="password" name="access_token" class="form-control form-control-sm" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('web/vtual.credentials.refresh_token') }}</label>
                    <input type="password" name="refresh_token" class="form-control form-control-sm" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('web/vtual.credentials.expires_at') }}</label>
                    <input type="text" name="expires_at" class="form-control form-control-sm" autocomplete="off">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm mt-3">{{ __('web/vtual.credentials.save') }}</button>
        </form>
    </x-adminlte4::card>

    <x-slot:scripts>
        <script>
            (function () {
                const typeSelect = document.getElementById('credential-type');
                if (!typeSelect) return;

                const toggle = () => {
                    const type = typeSelect.value;
                    document.querySelectorAll('[data-secret-fields]').forEach((el) => {
                        const match = el.getAttribute('data-secret-fields') === type;
                        el.classList.toggle('d-none', !match);
                        el.querySelectorAll('input').forEach((input) => {
                            input.disabled = !match;
                        });
                    });
                };

                typeSelect.addEventListener('change', toggle);
                toggle();
            })();
        </script>
    </x-slot:scripts>
</x-adminlte4::layouts.app>
