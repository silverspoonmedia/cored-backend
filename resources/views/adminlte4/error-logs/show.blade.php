<x-adminlte4::layouts.app :title="__('web/error-log.show_title')">
    <x-adminlte4::card :title="__('web/error-log.show_card_title')">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('web/error-log.fields.status') }}</dt>
            <dd class="col-sm-9">{{ $entry['status_label'] ?? $entry['status'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/error-log.fields.level') }}</dt>
            <dd class="col-sm-9">
                <span class="badge text-bg-danger">{{ $entry['level'] ?? '—' }}</span>
                <span class="badge text-bg-light border">{{ $entry['type'] ?? '—' }}</span>
            </dd>

            <dt class="col-sm-3">{{ __('web/error-log.fields.occurrence_count') }}</dt>
            <dd class="col-sm-9">{{ $entry['occurrence_count'] ?? 1 }}</dd>

            <dt class="col-sm-3">{{ __('web/error-log.fields.message') }}</dt>
            <dd class="col-sm-9">{{ $entry['message'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/error-log.fields.exception_class') }}</dt>
            <dd class="col-sm-9"><code>{{ $entry['exception_class'] ?? '—' }}</code></dd>

            <dt class="col-sm-3">{{ __('web/error-log.fields.location') }}</dt>
            <dd class="col-sm-9">
                @if (! empty($entry['file']))
                    <code>{{ $entry['file'] }}@if (! empty($entry['line'])):{{ $entry['line'] }}@endif</code>
                @else
                    —
                @endif
            </dd>

            <dt class="col-sm-3">{{ __('web/error-log.fields.first_seen_at') }}</dt>
            <dd class="col-sm-9">{{ $entry['first_seen_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/error-log.fields.last_seen_at') }}</dt>
            <dd class="col-sm-9">{{ $entry['last_seen_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/error-log.fields.fingerprint') }}</dt>
            <dd class="col-sm-9"><code class="small">{{ $entry['fingerprint'] ?? '—' }}</code></dd>

            @if (! empty($entry['resolved_by']['name']))
                <dt class="col-sm-3">{{ __('web/error-log.fields.resolved_by') }}</dt>
                <dd class="col-sm-9">
                    {{ $entry['resolved_by']['name'] }}
                    <span class="text-muted">({{ $entry['resolved_by']['email'] ?? '' }})</span>
                    @if (! empty($entry['resolved_at']))
                        <span class="text-muted small">· {{ $entry['resolved_at'] }}</span>
                    @endif
                </dd>
            @endif
        </dl>

        @if (! empty($entry['context']))
            <hr>
            <h6 class="mb-3">{{ __('web/error-log.sections.context') }}</h6>
            <pre class="bg-body-tertiary border rounded p-3 small mb-0">{{ json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        @endif

        @if (! empty($entry['trace']))
            <hr>
            <h6 class="mb-3">{{ __('web/error-log.sections.trace') }}</h6>
            <pre class="bg-body-tertiary border rounded p-3 small mb-0" style="max-height: 28rem; overflow: auto;">{{ $entry['trace'] }}</pre>
        @endif

        <x-slot:actions>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('error-logs.index') }}" class="btn btn-outline-secondary btn-sm">
                    {{ __('web/error-log.back_to_list') }}
                </a>

                @php $current = $entry['status'] ?? 'open'; @endphp

                @if ($current !== 'resolved')
                    <form method="post" action="{{ route('error-logs.status', $entry['id']) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="resolved">
                        <button type="submit" class="btn btn-success btn-sm">{{ __('web/error-log.actions.resolve') }}</button>
                    </form>
                @endif

                @if ($current !== 'ignored')
                    <form method="post" action="{{ route('error-logs.status', $entry['id']) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="ignored">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('web/error-log.actions.ignore') }}</button>
                    </form>
                @endif

                @if ($current !== 'open')
                    <form method="post" action="{{ route('error-logs.status', $entry['id']) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="open">
                        <button type="submit" class="btn btn-outline-warning btn-sm">{{ __('web/error-log.actions.reopen') }}</button>
                    </form>
                @endif
            </div>
        </x-slot:actions>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
