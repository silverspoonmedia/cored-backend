@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'hint' => null,
    'select2' => true,
    'ajaxUrl' => null,
    'ajaxParams' => [],
    'minimumInputLength' => 0,
    'autosubmit' => false,
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
        @if ($select2)
            data-monolith-select2="1"
            data-placeholder="{{ $placeholder }}"
            data-minimum-input-length="{{ $minimumInputLength }}"
            @if ($ajaxUrl)
                data-ajax-url="{{ $ajaxUrl }}"
                data-ajax-params='@json($ajaxParams)'
            @endif
            @if ($autosubmit)
                data-autosubmit="1"
            @endif
        @endif
        {{ $attributes->merge(['class' => 'form-select'.($hasError ? ' is-invalid' : '')]) }}
    >
        @if ($placeholder !== null && ! $ajaxUrl)
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

    @if ($select2)
        @push('scripts')
            <x-adminlte4::partials.select2-scripts />
        @endpush
    @endif
</div>
