@php
    $entries = $entries ?? collect();
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.blocklist_title')">
    <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">{{ __('web/file-hosting.blocklist_title') }}</h1>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-header">
                <strong>{{ __('web/file-hosting.blocklist_add') }}</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('file-hosting.blocklist.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label">{{ __('web/file-hosting.blocklist_sha256') }}</label>
                        <input type="text" name="sha256" class="form-control font-monospace" maxlength="64" required
                               placeholder="{{ __('web/file-hosting.blocklist_sha256_placeholder') }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ __('web/file-hosting.blocklist_reason') }}</label>
                        <input type="text" name="reason" class="form-control" maxlength="255"
                               placeholder="{{ __('web/file-hosting.blocklist_reason_placeholder') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-danger w-100">{{ __('web/file-hosting.blocklist_add') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <strong>{{ __('web/file-hosting.blocklist_entries') }}</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('web/file-hosting.blocklist_sha256') }}</th>
                            <th>{{ __('web/file-hosting.blocklist_reason') }}</th>
                            <th>{{ __('web/file-hosting.blocklist_added') }}</th>
                            <th class="text-end">{{ __('web/file-hosting.blocklist_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="font-monospace small">{{ $entry->sha256 }}</td>
                                <td>{{ $entry->reason ?? '—' }}</td>
                                <td>{{ $entry->created_at?->toDayDateTimeString() }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('file-hosting.blocklist.destroy', $entry->id) }}"
                                          data-swal-confirm="{{ __('web/file-hosting.blocklist_confirm_remove') }}" class="d-inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            {{ __('web/file-hosting.blocklist_remove') }}
                                            <span class="visually-hidden" data-form-submit-spinner><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-adminlte4::empty-state icon="bi-shield-slash" :title="__('web/file-hosting.blocklist_empty')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-adminlte4::layouts.app>
