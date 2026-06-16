@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'hint' => null,
])
@php($id = $attributes->get('id', $name))
@php($hasError = $errors->has($name))
@php($current = old($name, $selected))

<div class="mb-3" data-form-field="{{ $name }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}@if ($attributes->has('required'))<span class="text-danger">*</span>@endif
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $id }}"
        {{ $attributes->merge(['class' => 'form-select'.($hasError ? ' is-invalid' : '')]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>

    <x-adminlte4::form.error :name="$name" />

    @if ($hint)
        <small class="form-text text-muted">{{ $hint }}</small>
    @endif
</div>
