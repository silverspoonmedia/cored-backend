<x-adminlte4::layouts.app :title="__('web/vtual.profiles.title')">
    <x-adminlte4::card :title="__('web/vtual.profiles.title')">
        <p class="text-muted small mb-3">{{ __('web/vtual.profiles.subtitle') }}</p>
        @include('adminlte4.vtual.partials.nav')

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.display_name') }}</th>
                        <th>{{ __('web/vtual.slug') }}</th>
                        <th>{{ __('web/vtual.status') }}</th>
                        <th>{{ __('web/vtual.profiles.channels_count') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profiles as $profile)
                        <tr>
                            <td>{{ $profile['display_name'] ?? '' }}</td>
                            <td><code>{{ $profile['slug'] ?? '' }}</code></td>
                            <td><span class="badge text-bg-light border">{{ $profile['status'] ?? '' }}</span></td>
                            <td>{{ $profile['channels_count'] ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('vtual.profiles.show', $profile['id']) }}" class="btn btn-primary btn-sm">
                                    {{ __('web/vtual.manage') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-adminlte4::empty-state icon="bi-person-badge" :title="__('web/vtual.profiles.empty')" />
                                <p class="text-center mt-2">
                                    <a href="{{ route('vtual.claims.index') }}" class="btn btn-outline-primary btn-sm">
                                        {{ __('web/vtual.nav.claim') }}
                                    </a>
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
