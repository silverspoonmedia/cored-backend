@props([
    'title' => null,
])
<div {{ $attributes->merge(['class' => 'card mb-4']) }}>
    @if ($title || isset($actions))
        <div class="card-header d-flex align-items-center gap-2">
            <h3 class="card-title mb-0 flex-grow-1 float-none">{{ $title }}</h3>
            @if (isset($actions) && $actions->isNotEmpty())
                <div class="card-tools float-none me-0 flex-shrink-0">{{ $actions }}</div>
            @endif
        </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
