{{-- Shared settings group form. Keys use underscore field names (dots → _). --}}
@can('manage settings')
    <x-adminlte4::form.ajax :action="$action" method="PUT">
        @foreach ($settings as $key => $meta)
            @php
                $field = str_replace('.', '_', $key);
                $type = $meta['type'] ?? 'string';
                $value = $meta['value'] ?? null;
                $source = $meta['source'] ?? 'config';
                $label = __('web/settings.fields.'.$key);
                $hint = __('web/settings.hints.'.$key);
            @endphp

            <div class="mb-3">
                @if ($type === 'bool')
                    <div class="form-check form-switch">
                        <input
                            type="hidden"
                            name="{{ $field }}"
                            value="0"
                        >
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="setting-{{ $field }}"
                            name="{{ $field }}"
                            value="1"
                            @checked((bool) $value)
                        >
                        <label class="form-check-label" for="setting-{{ $field }}">
                            {{ $label }}
                        </label>
                    </div>
                @elseif ($type === 'array')
                    <label class="form-label" for="setting-{{ $field }}">{{ $label }}</label>
                    <textarea
                        class="form-control"
                        id="setting-{{ $field }}"
                        name="{{ $field }}"
                        rows="2"
                    >{{ is_array($value) ? implode(', ', $value) : '' }}</textarea>
                @else
                    <x-adminlte4::form.input
                        :name="$field"
                        :label="$label"
                        :type="in_array($type, ['int', 'float'], true) ? 'number' : 'text'"
                        :value="$value"
                    />
                @endif

                @if ($hint !== 'web/settings.hints.'.$key)
                    <div class="form-text">{{ $hint }}</div>
                @endif

                <div class="form-text text-muted">
                    {{ __('web/settings.source', ['source' => $source]) }}
                </div>
            </div>
        @endforeach

        <x-adminlte4::form.submit :label="__('ui.actions.save')" />
    </x-adminlte4::form.ajax>
@else
    <div class="alert alert-info mb-0">{{ __('web/settings.read_only') }}</div>
    <dl class="row mt-3 mb-0">
        @foreach ($settings as $key => $meta)
            <dt class="col-sm-5">{{ __('web/settings.fields.'.$key) }}</dt>
            <dd class="col-sm-7">
                @if (($meta['type'] ?? '') === 'bool')
                    {{ ($meta['value'] ?? false) ? __('ui.yes') : __('ui.no') }}
                @elseif (($meta['type'] ?? '') === 'array')
                    {{ is_array($meta['value'] ?? null) ? implode(', ', $meta['value']) : '—' }}
                @else
                    {{ $meta['value'] ?? '—' }}
                @endif
            </dd>
        @endforeach
    </dl>
@endcan
