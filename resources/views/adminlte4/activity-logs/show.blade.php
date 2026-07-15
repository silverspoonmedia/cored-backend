<x-adminlte4::layouts.app :title="__('web/activity-log.show_title')">
    <x-adminlte4::card :title="__('web/activity-log.show_card_title', ['id' => $entry['id'] ?? ''])">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('web/activity-log.fields.log_name') }}</dt>
            <dd class="col-sm-9">{{ $entry['log_name'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/activity-log.fields.event') }}</dt>
            <dd class="col-sm-9">{{ $entry['event_label'] ?? $entry['event'] ?? $entry['description'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/activity-log.fields.subject') }}</dt>
            <dd class="col-sm-9">{{ $entry['subject_label'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/activity-log.fields.causer') }}</dt>
            <dd class="col-sm-9">
                @if (! empty($entry['causer']['name']))
                    {{ $entry['causer']['name'] }}
                    <span class="text-muted">({{ $entry['causer']['email'] ?? '' }})</span>
                @else
                    —
                @endif
            </dd>

            <dt class="col-sm-3">{{ __('web/activity-log.fields.created_at') }}</dt>
            <dd class="col-sm-9">{{ $entry['created_at'] ?? '—' }}</dd>
        </dl>

        @if (! empty($entry['attribute_changes']))
            <hr>
            <h6 class="mb-3">{{ __('web/activity-log.sections.changes') }}</h6>
            @php
                $newValues = $entry['attribute_changes']['attributes'] ?? [];
                $oldValues = $entry['attribute_changes']['old'] ?? [];
                $attributes = array_unique([...array_keys($newValues), ...array_keys($oldValues)]);
            @endphp
            @if (count($attributes) > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/activity-log.columns.attribute') }}</th>
                                <th>{{ __('web/activity-log.columns.old_value') }}</th>
                                <th>{{ __('web/activity-log.columns.new_value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attributes as $attribute)
                                <tr>
                                    <td><code>{{ $attribute }}</code></td>
                                    <td class="text-muted small">{{ is_scalar($oldValues[$attribute] ?? null) ? ($oldValues[$attribute] ?? '—') : json_encode($oldValues[$attribute] ?? null) }}</td>
                                    <td class="small">{{ is_scalar($newValues[$attribute] ?? null) ? ($newValues[$attribute] ?? '—') : json_encode($newValues[$attribute] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        @if (! empty($entry['properties']))
            <hr>
            <h6 class="mb-3">{{ __('web/activity-log.sections.properties') }}</h6>
            <pre class="bg-body-tertiary border rounded p-3 small mb-0">{{ json_encode($entry['properties'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif

        <x-slot:actions>
            <a href="{{ route('activity-logs.index') }}" class="btn btn-outline-secondary btn-sm">
                {{ __('web/activity-log.back_to_list') }}
            </a>
        </x-slot:actions>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
