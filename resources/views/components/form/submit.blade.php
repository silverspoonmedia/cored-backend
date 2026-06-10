@props([
    'label' => 'Simpan',
])

<button type="submit" {{ $attributes }}>
    <span data-form-submit-label>{{ $label }}</span>
    <span class="spinner-border spinner-border-sm d-none ms-1" data-form-submit-spinner role="status" aria-hidden="true"></span>
</button>
