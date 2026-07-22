@php
    $canManageAny = auth()->user()?->can('manage any vtual resource') ?? false;
@endphp
<x-adminlte4::layouts.app :title="__('web/vtual.claims.title')">
    <x-adminlte4::card :title="__('web/vtual.claims.title')">
        <p class="text-muted small mb-3">{{ __('web/vtual.claims.subtitle') }}</p>
        @include('adminlte4.vtual.partials.nav')

        <div class="row g-3">
            <div class="col-lg-7">
                <h5 class="mb-2">{{ __('web/vtual.claims.claimable') }}</h5>
                @forelse ($claimable as $creator)
                    @php
                        $channels = collect($creator['channels'] ?? []);
                    @endphp
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong>{{ $creator['display_name'] ?? '' }}</strong>
                                <div class="small text-muted"><code>{{ $creator['slug'] ?? '' }}</code></div>
                            </div>
                            <a href="{{ route('vtual.creators.show', $creator['id']) }}" class="btn btn-outline-info btn-sm">
                                {{ __('web/vtual.view') }}
                            </a>
                        </div>
                        @if ($channels->isEmpty())
                            <p class="text-muted small mb-0">{{ __('web/vtual.claims.no_channels') }}</p>
                        @else
                            <form method="POST" action="{{ route('vtual.claims.store') }}" class="row g-2 align-items-end">
                                @csrf
                                <input type="hidden" name="creator_id" value="{{ $creator['id'] }}">
                                <div class="col-md-8">
                                    <label class="form-label">{{ __('web/vtual.claims.channel') }}</label>
                                    <select name="channel_id" class="form-select form-select-sm" required>
                                        <option value="">{{ __('web/vtual.claims.select_channel') }}</option>
                                        @foreach ($channels as $channel)
                                            <option value="{{ $channel['id'] ?? '' }}">
                                                {{ $channel['platform']['name'] ?? $channel['platform']['key'] ?? 'platform' }}
                                                —
                                                {{ $channel['handle'] ?? $channel['display_name'] ?? $channel['external_id'] ?? '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('web/vtual.claims.start') }}</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <x-adminlte4::empty-state icon="bi-person-check" :title="__('web/vtual.claims.empty_claimable')" />
                @endforelse
            </div>

            <div class="col-lg-5">
                <h5 class="mb-2">{{ __('web/vtual.claims.mine') }}</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/vtual.display_name') }}</th>
                                <th>{{ __('web/vtual.claims.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mine as $claim)
                                <tr>
                                    <td>{{ $claim['creator']['display_name'] ?? $claim['creator_id'] ?? '' }}</td>
                                    <td><span class="badge text-bg-light border">{{ $claim['status'] ?? '' }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('vtual.claims.show', $claim['id']) }}" class="btn btn-outline-primary btn-sm">
                                            {{ __('web/vtual.view') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-adminlte4::empty-state icon="bi-hourglass" :title="__('web/vtual.claims.empty_mine')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
