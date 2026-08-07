<x-adminlte4::layouts.app :title="__('web/vtual-profiles.page_title')">
    <x-adminlte4::card :title="__('web/vtual-profiles.card_title')">
        <p class="text-muted small mb-3">{{ __('web/vtual-profiles.subtitle') }}</p>

        @if (count($profiles) === 0)
            <p class="text-muted mb-0">{{ __('web/vtual-profiles.empty') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">{{ __('web/vtual-profiles.card_title') }}</caption>
                    <thead>
                        <tr>
                            <th>{{ __('web/vtual-profiles.columns.avatar') }}</th>
                            <th>{{ __('web/vtual-profiles.columns.display_name') }}</th>
                            <th>{{ __('web/vtual-profiles.columns.slug') }}</th>
                            <th>{{ __('web/vtual-profiles.columns.channels') }}</th>
                            <th class="text-end">{{ __('web/vtual-profiles.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($profiles as $profile)
                            <tr>
                                <td>
                                    @if (! empty($profile['avatar_url']))
                                        <img
                                            src="{{ $profile['avatar_url'] }}"
                                            alt=""
                                            class="rounded-circle"
                                            width="40"
                                            height="40"
                                        >
                                    @else
                                        <span class="avatar avatar-sm bg-light border rounded-circle d-inline-flex align-items-center justify-content-center">
                                            <i class="bi bi-person text-muted" aria-hidden="true"></i>
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $profile['display_name'] ?? '' }}</td>
                                <td><code class="small">{{ $profile['slug'] ?? '' }}</code></td>
                                <td>{{ (int) ($profile['channels_count'] ?? 0) }}</td>
                                <td class="text-end">
                                    <a
                                        href="{{ route('vtual.profiles.show', $profile['slug'] ?? '') }}"
                                        class="btn btn-primary btn-sm me-1"
                                    >
                                        {{ __('web/vtual.manage') }}
                                    </a>
                                    <a
                                        href="{{ $profile['profile_url'] ?? route('vtual.vtuber.show', $profile['slug'] ?? '') }}"
                                        class="btn btn-outline-primary btn-sm"
                                    >
                                        {{ __('web/vtual-profiles.view_profile') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
