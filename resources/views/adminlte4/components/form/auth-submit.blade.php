@props([
    'label',
    'class' => 'btn btn-primary',
])

<x-adminlte4::form.hcaptcha />

<div class="d-grid">
    <x-adminlte4::form.submit :label="$label" :class="$class" />
</div>
