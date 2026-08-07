@php
    $contents = collect($profile['contents'] ?? [])
        ->sortByDesc(fn (array $content): string => $content['published_at'] ?? '')
        ->values()
        ->all();
@endphp

@if (count($contents) === 0)
    <p class="text-muted text-center mb-0">{{ __('web/vtual-vtuber.contents_empty') }}</p>
@else
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3" role="list"
         aria-label="{{ __('web/vtual-vtuber.table_caption_contents') }}">
        @foreach ($contents as $content)
            @php
                $type = $content['type'] ?? 'unknown';
                $status = $content['status'] ?? 'unknown';
                $isLive = $status === 'live' || $type === 'live';
                $title = $content['title'] ?? '—';
                $platformLabel = $content['platform']['name'] ?? $content['platform']['key'] ?? null;
            @endphp
            <div class="col min-w-0" role="listitem">
                <div class="card vtuber-content-card h-100 position-relative min-w-0 d-flex flex-column">
                    <div class="ratio ratio-16x9 bg-body-secondary overflow-hidden rounded-top">
                        @if (! empty($content['thumbnail_url']))
                            <img src="{{ $content['thumbnail_url'] }}" alt=""
                                 class="card-img-top object-fit-cover rounded-top"
                                 loading="lazy"
                                 referrerpolicy="no-referrer">
                        @else
                            <div class="d-flex align-items-center justify-content-center h-100 rounded-top" aria-hidden="true">
                                <i class="bi bi-film fs-1 text-muted"></i>
                            </div>
                        @endif
                        @if ($isLive)
                            <span class="position-absolute top-0 start-0 m-2 badge text-bg-danger">
                                <i class="bi bi-broadcast me-1" aria-hidden="true"></i>
                                {{ __('web/vtual-vtuber.content_status.live') }}
                            </span>
                        @endif
                    </div>

                    <div class="card-body p-2 min-w-0 position-relative z-1 d-flex flex-column flex-grow-1">
                        <div class="vtuber-content-card__title">
                            <h4 class="h6 card-title monolith-line-clamp-2 mb-0" title="{{ $title }}">{{ $title }}</h4>
                        </div>
                        <div class="vtuber-content-card__meta">
                            <p class="card-text small text-secondary mb-0 text-truncate">
                                {{ $content['published_at_formatted'] ?? $content['published_at'] ?? '—' }}
                                @if ($platformLabel)
                                    · {{ $platformLabel }}
                                @endif
                            </p>
                        </div>
                        <div class="vtuber-content-card__badges">
                            <div class="d-flex flex-wrap align-items-center gap-1 w-100">
                                <span class="badge text-bg-light border text-truncate">
                                    {{ __('web/vtual-vtuber.content_type.'.$type) }}
                                </span>
                                @if (! $isLive)
                                    <span class="badge text-bg-secondary text-truncate">
                                        {{ __('web/vtual-vtuber.content_status.'.$status) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if (! empty($content['url']))
                        <a href="{{ $content['url'] }}" class="stretched-link" target="_blank" rel="noopener noreferrer"
                           aria-label="{{ __('web/vtual-vtuber.open_link') }}: {{ $title }}"></a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
