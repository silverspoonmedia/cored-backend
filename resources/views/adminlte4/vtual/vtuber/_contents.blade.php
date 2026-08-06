@php
    $contents = $profile['contents'] ?? [];
@endphp

<h5 class="mb-2">{{ __('web/vtual-vtuber.section_contents') }}</h5>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0" aria-label="{{ __('web/vtual-vtuber.table_caption_contents') }}">
        <caption class="visually-hidden">{{ __('web/vtual-vtuber.table_caption_contents') }}</caption>
        <thead>
            <tr>
                <th>{{ __('web/vtual-vtuber.columns.thumbnail') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.title') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.platform') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.type') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.status') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.published_at') }}</th>
                <th>{{ __('web/vtual-vtuber.columns.link') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($contents as $content)
                @php
                    $type = $content['type'] ?? 'unknown';
                    $status = $content['status'] ?? 'unknown';
                @endphp
                <tr>
                    <td class="text-nowrap">
                        @if (!empty($content['thumbnail_url']))
                            <img src="{{ $content['thumbnail_url'] }}" alt=""
                                 class="rounded object-fit-cover"
                                 width="80" height="45" loading="lazy"
                                 referrerpolicy="no-referrer">
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-break">{{ $content['title'] ?? '—' }}</td>
                    <td>{{ $content['platform']['name'] ?? $content['platform']['key'] ?? '—' }}</td>
                    <td>
                        <span class="badge text-bg-light border">
                            {{ __('web/vtual-vtuber.content_type.'.$type) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge text-bg-secondary">
                            {{ __('web/vtual-vtuber.content_status.'.$status) }}
                        </span>
                    </td>
                    <td class="text-nowrap small text-muted">
                        {{ $content['published_at_formatted'] ?? $content['published_at'] ?? '—' }}
                    </td>
                    <td class="text-nowrap">
                        @if (!empty($content['url']))
                            <a href="{{ $content['url'] }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                                {{ __('web/vtual-vtuber.open_link') }}
                            </a>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-muted">{{ __('web/vtual-vtuber.contents_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
