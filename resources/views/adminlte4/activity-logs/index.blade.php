<x-adminlte4::layouts.app :title="__('web/activity-log.title')">
    <x-adminlte4::card :title="__('web/activity-log.list_title')">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/activity-log.columns.event') }}</th>
                        <th>{{ __('web/activity-log.columns.subject') }}</th>
                        <th>{{ __('web/activity-log.columns.causer') }}</th>
                        <th>{{ __('web/activity-log.columns.time') }}</th>
                        <th class="text-end">{{ __('web/activity-log.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>
                                <span class="badge text-bg-light border">{{ $entry['log_name'] ?? '—' }}</span>
                                {{ $entry['event_label'] ?? $entry['event'] ?? $entry['description'] }}
                            </td>
                            <td class="text-muted small">{{ $entry['subject_label'] ?? '—' }}</td>
                            <td class="text-muted small">{{ data_get($entry, 'causer.name', '—') }}</td>
                            <td class="text-muted small">{{ $entry['created_at'] ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('activity-logs.show', $entry['id']) }}" class="btn btn-outline-secondary btn-sm">
                                    {{ __('web/activity-log.view_detail') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">{{ __('web/activity-log.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
