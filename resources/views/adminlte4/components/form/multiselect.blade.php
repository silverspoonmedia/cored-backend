@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => [],
    'hint' => null,
    'select2' => true,
    'ajaxUrl' => null,
    'ajaxParams' => [],
    'minimumInputLength' => 0,
    'placeholder' => null,
])
@php($id = $attributes->get('id', $name))
@php($hasError = $errors->has($name))
@php($current = (array) old($name, $selected))

<div class="mb-3" data-form-field="{{ $name }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    @endif

    <select
        name="{{ $name }}[]"
        id="{{ $id }}"
        multiple
        @if ($select2)
            data-monolith-select2="1"
            data-placeholder="{{ $placeholder }}"
            data-minimum-input-length="{{ $minimumInputLength }}"
            @if ($ajaxUrl)
                data-ajax-url="{{ $ajaxUrl }}"
                data-ajax-params='@json($ajaxParams)'
            @endif
        @endif
        {{ $attributes->merge(['class' => 'form-select'.($hasError ? ' is-invalid' : '')]) }}
    >
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, array_map('strval', $current), true))>{{ $text }}</option>
        @endforeach
    </select>

    <x-adminlte4::form.error :name="$name" class="d-block" />

    @if ($hint)
        <small class="form-text text-muted">{{ $hint }}</small>
    @endif

    @if ($select2)
        @push('scripts')
            <x-adminlte4::partials.select2-scripts />
        @endpush
    @endif
</div>
