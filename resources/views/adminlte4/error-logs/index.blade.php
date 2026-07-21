<x-adminlte4::layouts.app :title="__('web/error-log.title')">
    <x-adminlte4::card :title="__('web/error-log.list_title')">
        <form method="get" action="{{ route('error-logs.index') }}" class="row g-2 mb-3" aria-label="{{ __('web/error-log.filters.apply') }}">
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" aria-label="{{ __('web/error-log.filters.all_statuses') }}">
                    <option value="">{{ __('web/error-log.filters.all_statuses') }}</option>
                    @foreach (['open', 'resolved', 'ignored'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                            {{ __('web/error-log.statuses.'.$status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="level" class="form-select form-select-sm" aria-label="{{ __('web/error-log.filters.all_levels') }}">
                    <option value="">{{ __('web/error-log.filters.all_levels') }}</option>
                    @foreach (['error', 'critical', 'alert', 'emergency'] as $level)
                        <option value="{{ $level }}" @selected(($filters['level'] ?? '') === $level)>
                            {{ $level }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm" aria-label="{{ __('web/error-log.filters.all_types') }}">
                    <option value="">{{ __('web/error-log.filters.all_types') }}</option>
                    @foreach (['exception', 'log'] as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                            {{ __('web/error-log.types.'.$type) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm"
                       placeholder="{{ __('web/error-log.filters.search') }}" aria-label="{{ __('web/error-log.filters.search') }}">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">{{ __('web/error-log.filters.apply') }}</button>
                <a href="{{ route('error-logs.index') }}" class="btn btn-outline-secondary btn-sm" aria-label="{{ __('ui.actions.reset') }}">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/error-log.columns.level') }}</th>
                        <th>{{ __('web/error-log.columns.message') }}</th>
                        <th>{{ __('web/error-log.columns.count') }}</th>
                        <th>{{ __('web/error-log.columns.status') }}</th>
                        <th>{{ __('web/error-log.columns.last_seen') }}</th>
                        <th class="text-end">{{ __('web/error-log.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>
                                <span class="badge text-bg-{{ ($entry['level'] ?? '') === 'error' ? 'danger' : 'dark' }}">
                                    {{ $entry['level'] ?? '—' }}
                                </span>
                                <span class="badge text-bg-light border">{{ $entry['type'] ?? '—' }}</span>
                            </td>
                            <td>
                                <div class="fw-medium text-truncate monolith-truncate-md" title="{{ $entry['message'] ?? '' }}">
                                    {{ $entry['message'] ?? '—' }}
                                </div>
                                @if (! empty($entry['exception_class']))
                                    <div class="text-muted small text-truncate monolith-truncate-md">
                                        {{ $entry['exception_class'] }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $entry['occurrence_count'] ?? 1 }}</td>
                            <td>
                                @php
                                    $status = $entry['status'] ?? 'open';
                                    $statusClass = match ($status) {
                                        'resolved' => 'success',
                                        'ignored' => 'secondary',
                                        default => 'warning',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $statusClass }}">
                                    {{ $entry['status_label'] ?? $status }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $entry['last_seen_at'] ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('error-logs.show', $entry['id']) }}" class="btn btn-outline-secondary btn-sm">
                                    {{ __('web/error-log.view_detail') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-adminlte4::empty-state icon="bi-bug" :title="__('web/error-log.empty')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
