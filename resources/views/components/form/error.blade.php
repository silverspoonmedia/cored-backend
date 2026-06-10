@props(['name'])

{{--
    Single field validation message — shared by server render (@error) and the
    AJAX form script (data-field-error). Keep one per field, immediately after
    the control (Bootstrap invalid-feedback sibling rule).
--}}
@php($messages = $errors->get($name))

<div
    {{ $attributes->merge(['class' => 'invalid-feedback']) }}
    data-field-error="{{ $name }}"
    @if (! empty($messages)) style="display: block;" @endif
>
    @if (! empty($messages))
        {{ $messages[0] }}
    @endif
</div>
