@php
    $channels = $profile['channels'] ?? [];
@endphp

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title mb-0">{{ __('web/vtual-vtuber.section_channels') }}</h3>
    </div>
    <div class="card-body p-2">
        @forelse ($channels as $channel)
            @php
                $platformKey = $channel['platform']['key'] ?? '';
                $platformName = $channel['platform']['name'] ?? $platformKey;
                $handle = $channel['handle'] ?? '';
                $channelName = $channel['display_name'] ?? $handle;
            @endphp
            <div class="card border {{ ! $loop->last ? 'mb-2' : '' }}">
                <div class="card-body p-3 small">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        @if (! empty($channel['avatar_url']))
                            <img src="{{ $channel['avatar_url'] }}" alt="{{ $channelName }}"
                                 class="rounded-circle object-fit-cover flex-shrink-0"
                                 width="48" height="48" loading="lazy"
                                 referrerpolicy="no-referrer">
                        @else
                            <div class="rounded-circle bg-body-secondary flex-shrink-0 d-flex align-items-center justify-content-center"
                                 style="width: 48px; height: 48px;" aria-hidden="true">
                                <i class="bi bi-broadcast text-muted"></i>
                            </div>
                        @endif
                        @if ($platformName !== '')
                            <span class="badge text-bg-light border ms-auto">{{ $platformName }}</span>
                        @endif
                    </div>

                    <p class="fw-semibold mb-1 text-break">{{ $channelName }}</p>

                    @if ($handle !== '')
                        <p class="text-secondary mb-2">
                            <i class="bi bi-at" aria-hidden="true"></i>{{ $handle }}
                        </p>
                    @endif

                    @if (! empty($channel['subscriber_count_formatted']))
                        <p class="text-secondary mb-2">
                            <i class="bi bi-people me-1" aria-hidden="true"></i>
                            {{ __('web/vtual-vtuber.subscribers', ['count' => $channel['subscriber_count_formatted']]) }}
                        </p>
                    @endif

                    @if (! empty($channel['url']))
                        <a href="{{ $channel['url'] }}" class="btn btn-outline-secondary btn-sm w-100 mt-2" target="_blank" rel="noopener noreferrer">
                            {{ __('web/vtual-vtuber.open_link') }}
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted mb-0 px-1">{{ __('web/vtual-vtuber.channels_empty') }}</p>
        @endforelse
    </div>
</div>
