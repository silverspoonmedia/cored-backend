@props([
    'action',
    'method' => 'POST',
    'showAlert' => true,
])

{{--
    AJAX-enabled form: submits via fetch (no full page reload on 422).
    Pair with <x-adminlte4::form.input>, <x-adminlte4::form.select>, etc. — they expose data-form-field
    and <x-adminlte4::form.error> for DRY client/server validation display.
--}}
<form
    method="POST"
    action="{{ $action }}"
    data-ajax-form
    novalidate
    {{ $attributes }}
>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    @if ($showAlert)
        <div class="alert d-none mb-3" data-form-alert role="alert"></div>
    @endif

    {{ $slot }}
</form>
