@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'icon' => null,
    'hint' => null,
    'toggleable' => null,
])
@php($id = $attributes->get('id', $name))
@php($hasError = $errors->has($name))
@php($canToggle = ($toggleable ?? $type === 'password') && $type === 'password')
@php($useInputGroup = $icon || $canToggle)

<div class="mb-3" data-form-field="{{ $name }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}@if ($attributes->has('required'))<span class="text-danger">*</span>@endif
        </label>
    @endif

    @if ($useInputGroup)
        <div class="input-group @if ($hasError) has-validation @endif">
            <input
                type="{{ $type }}"
                name="{{ $name }}"
                id="{{ $id }}"
                @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
                {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}
            />
            @if ($canToggle)
                <button
                    type="button"
                    class="input-group-text"
                    data-password-toggle
                    aria-label="Tampilkan kata sandi"
                    aria-pressed="false"
                >
                    <i class="bi bi-eye-slash" aria-hidden="true"></i>
                </button>
            @endif
            @if ($icon)
                <span class="input-group-text"><i class="bi {{ $icon }}"></i></span>
            @endif
            <x-form.error :name="$name" />
        </div>
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $id }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}
        />
        <x-form.error :name="$name" />
    @endif

    @if ($hint)
        <small class="form-text text-muted">{{ $hint }}</small>
    @endif
</div>

@once
    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-password-toggle]');
            if (!button) {
                return;
            }

            const input = button.closest('.input-group')?.querySelector('input');
            if (!input) {
                return;
            }

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', isHidden);
                icon.classList.toggle('bi-eye-slash', !isHidden);
            }

            button.setAttribute('aria-label', isHidden ? @json(__('ui.password.hide')) : @json(__('ui.password.show')));
            button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        });
    </script>
@endonce
