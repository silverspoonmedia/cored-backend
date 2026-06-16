@props([
    'name',
    'label',
    'translations' => [],
    'required' => false,
    'hint' => null,
])

@php
    $locales = config('localization.supported_locales', ['en', 'id']);
@endphp

<div class="mb-3">
    <label class="form-label d-block">
        {{ $label }}
        @if ($required)
            <span class="text-danger">*</span>
        @endif
    </label>

    @if ($hint)
        <div class="form-text mb-2">{{ $hint }}</div>
    @endif

    @foreach ($locales as $locale)
        @php
            $fieldName = "{$name}.{$locale}";
            $fieldId = str_replace(['.', '[', ']'], '_', "{$name}_{$locale}");
            $value = old("{$name}.{$locale}", $translations[$locale] ?? '');
            $hasError = $errors->has($fieldName) || $errors->has($name);
        @endphp

        <div class="mb-2" data-form-field="{{ $fieldName }}">
            <label for="{{ $fieldId }}" class="form-label small text-muted text-uppercase mb-1">
                {{ $locale }}
            </label>
            <input
                type="text"
                name="{{ $name }}[{{ $locale }}]"
                id="{{ $fieldId }}"
                value="{{ $value }}"
                @if ($required) required @endif
                class="form-control{{ $hasError ? ' is-invalid' : '' }}"
            />
            <x-adminlte4::form.error :name="$fieldName" />
        </div>
    @endforeach

    <x-adminlte4::form.error :name="$name" />
</div>
