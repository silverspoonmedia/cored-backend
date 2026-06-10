@props([
    'title' => null,
])
<div {{ $attributes->merge(['class' => 'card mb-4']) }}>
    @if ($title || isset($actions))
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">{{ $title }}</h3>
            @isset($actions)
                <div class="card-tools">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
