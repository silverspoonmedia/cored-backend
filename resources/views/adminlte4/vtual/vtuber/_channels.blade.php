@php
    $channels = $profile['channels'] ?? [];
@endphp

<h5 class="mb-2">{{ __('web/vtual-vtuber.section_channels') }}</h5>

<div class="table-responsive mb-4">
    <table class="table table-sm table-hover align-middle mb-0" aria-label="{{ __('web/vtual-vtuber.table_caption_channels') }}">
        <caption class="visually-hidden">{{ __('web/vtual-vtuber.table_caption_channels') }}</caption>
        <thead>
            <tr>
                <th>{{ __('web/vtual-vtuber.columns.avatar') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.platform') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.handle') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.display_name') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.link') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($channels as $channel)
                <tr>
                    <td class="text-nowrap">
                        @if (!empty($channel['avatar_url']))
                            <img src="{{ $channel['avatar_url'] }}" alt="{{ $channel['display_name'] ?? $channel['handle'] ?? '' }}"
                                 class="rounded-circle object-fit-cover"
                                 width="36" height="36" loading="lazy"
                                 referrerpolicy="no-referrer">
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>{{ $channel['platform']['name'] ?? $channel['platform']['key'] ?? '' }}</td>
                    <td>{{ $channel['handle'] ?? '—' }}</td>
                    <td>{{ $channel['display_name'] ?? '—' }}</td>
                    <td class="text-nowrap">
                        @if (!empty($channel['url']))
                            <a href="{{ $channel['url'] }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                                {{ __('web/vtual-vtuber.open_link') }}
                            </a>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-muted">{{ __('web/vtual-vtuber.channels_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
