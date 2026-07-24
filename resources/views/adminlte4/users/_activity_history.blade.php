@if (count($activityLogs ?? []) > 0)
    <x-adminlte4::card :title="__('web/activity-log.user_history_title')">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/activity-log.columns.event') }}</th>
                        <th>{{ __('web/activity-log.columns.causer') }}</th>
                        <th>{{ __('web/activity-log.columns.time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activityLogs as $entry)
                        <tr>
                            <td>
                                <a href="{{ route('activity-logs.show', $entry['id']) }}" class="text-decoration-none">
                                    {{ $entry['event_label'] ?? $entry['event'] ?? $entry['description'] }}
                                </a>
                            </td>
                            <td class="text-muted small">{{ data_get($entry, 'causer.name', '—') }}</td>
                            <td class="text-muted small">{{ $entry['created_at_human'] ?? $entry['created_at_formatted'] ?? $entry['created_at'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
@else
    <x-adminlte4::card :title="__('web/activity-log.user_history_title')">
        <x-adminlte4::empty-state icon="bi-clock-history" :message="__('web/activity-log.empty')" />
    </x-adminlte4::card>
@endif
