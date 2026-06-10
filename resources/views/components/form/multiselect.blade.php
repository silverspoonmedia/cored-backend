@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => [],
    'hint' => null,
])
@php($id = $attributes->get('id', $name))
@php($hasError = $errors->has($name))
@php($current = (array) old($name, $selected))

<div class="mb-3">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    @endif

    <select
        name="{{ $name }}[]"
        id="{{ $id }}"
        multiple
        {{ $attributes->merge(['class' => 'form-select'.($hasError ? ' is-invalid' : '')]) }}
    >
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, array_map('strval', $current), true))>{{ $text }}</option>
        @endforeach
    </select>

    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

    @if ($hint)
        <small class="form-text text-muted">{{ $hint }}</small>
    @endif
</div>
