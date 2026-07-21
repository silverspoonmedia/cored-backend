@props([
    'icon' => 'bi-inbox',
    'title' => null,
    'message' => null,
])

<div class="text-center text-muted py-5" role="status">
    <i class="bi {{ $icon }} d-block mb-2" style="font-size: 2.5rem; opacity: 0.4;" aria-hidden="true"></i>
    @if ($title)
        <p class="fw-semibold mb-1">{{ $title }}</p>
    @endif
    @if ($message)
        <p class="small mb-0">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
