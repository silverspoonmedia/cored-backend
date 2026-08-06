@props([
    'status' => '',
    'label' => null,
])

@php
    $badgeClass = match ($status) {
        'active' => 'text-bg-success',
        'pending' => 'text-bg-warning',
        'archived' => 'text-bg-secondary',
        'disabled' => 'text-bg-secondary',
        'revoked' => 'text-bg-dark',
        default => 'text-bg-light border',
    };
    $display = $label ?? $status;
@endphp
<span class="badge {{ $badgeClass }}">{{ $display }}</span>
