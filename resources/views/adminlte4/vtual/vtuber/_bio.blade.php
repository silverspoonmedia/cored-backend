@php
    $channels = $profile['channels'] ?? [];
    $contents = $profile['contents'] ?? [];
@endphp

<div class="d-flex align-items-start gap-3 mb-4">
    @if (!empty($profile['avatar_url']))
        <img src="{{ $profile['avatar_url'] }}" alt="{{ $profile['display_name'] ?? '' }}"
             class="rounded-circle object-fit-cover flex-shrink-0"
             width="96" height="96" loading="lazy"
             referrerpolicy="no-referrer">
    @else
        <div class="rounded-circle bg-body-secondary flex-shrink-0 d-flex align-items-center justify-content-center"
             style="width: 96px; height: 96px;" aria-hidden="true">
            <i class="bi bi-person-circle fs-1 text-muted"></i>
        </div>
    @endif
    <div class="flex-grow-1 min-w-0">
        <h2 class="h4 mb-1">{{ $profile['display_name'] ?? '' }}</h2>
        @if (!empty($profile['slug']))
            <p class="text-muted small mb-2"><code>{{ $profile['slug'] }}</code></p>
        @endif
        <p class="mb-0 text-break">
            @if (!empty($profile['bio']))
                {{ $profile['bio'] }}
            @else
                <span class="text-muted">{{ __('web/vtual-vtuber.bio_empty') }}</span>
            @endif
        </p>
    </div>
</div>
