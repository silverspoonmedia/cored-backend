@php
    use Illuminate\Support\Str;

    $channels = $profile['channels'] ?? [];
    $contents = $profile['contents'] ?? [];
    $channelCount = count($channels);
    $contentCount = count($contents);
    $liveCount = collect($contents)->where('status', 'live')->count();

    $displayName = $profile['display_name'] ?? '';
    $nameParts = preg_split('/\s+/', trim($displayName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($nameParts) >= 2) {
        $initials = Str::upper(Str::substr($nameParts[0], 0, 1).Str::substr($nameParts[1], 0, 1));
    } elseif ($displayName !== '') {
        $initials = Str::upper(Str::substr($displayName, 0, 2));
    } else {
        $initials = '?';
    }
@endphp

<div class="card">
    <div class="card-body text-center">
        @if (! empty($profile['avatar_url']))
            <img src="{{ $profile['avatar_url'] }}" alt="{{ $displayName }}"
                 class="rounded-circle object-fit-cover d-inline-block mb-3"
                 width="96" height="96" loading="lazy"
                 referrerpolicy="no-referrer">
        @else
            <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3"
                 style="width: 96px; height: 96px; font-size: 2rem;"
                 aria-hidden="true">
                {{ $initials }}
            </div>
        @endif

        <h3 class="h5 mb-0">{{ $displayName }}</h3>
        @if (! empty($profile['slug']))
            <p class="text-secondary mb-3"><code>{{ $profile['slug'] }}</code></p>
        @endif

        <ul class="list-group list-group-flush text-start small">
            <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-secondary">{{ __('web/vtual-vtuber.stat_channels') }}</span>
                <span class="fw-semibold">{{ $channelCount }}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-secondary">{{ __('web/vtual-vtuber.stat_contents') }}</span>
                <span class="fw-semibold">{{ $contentCount }}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-secondary">{{ __('web/vtual-vtuber.stat_live') }}</span>
                <span class="fw-semibold">{{ $liveCount }}</span>
            </li>
        </ul>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title mb-0">{{ __('web/vtual-vtuber.section_about') }}</h3>
    </div>
    <div class="card-body small">
        <p class="fw-semibold mb-1">
            <i class="bi bi-person-lines-fill me-1 text-secondary" aria-hidden="true"></i>
            {{ __('web/vtual-vtuber.section_bio') }}
        </p>
        <p class="text-secondary mb-3 text-break">
            @if (! empty($profile['bio']))
                {{ $profile['bio'] }}
            @else
                {{ __('web/vtual-vtuber.bio_empty') }}
            @endif
        </p>

        @if (! empty($profile['slug']))
            <p class="fw-semibold mb-1">
                <i class="bi bi-at me-1 text-secondary" aria-hidden="true"></i>
                {{ __('web/vtual-vtuber.slug_label') }}
            </p>
            <p class="text-secondary mb-0"><code>{{ $profile['slug'] }}</code></p>
        @endif
    </div>
</div>

@include(config('web.layout').'.vtual.vtuber._channels', ['profile' => $profile])
