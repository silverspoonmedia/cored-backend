@php
    $creatorId = $creator['id'] ?? '';
    $kinds = ['social', 'donation', 'website', 'other'];
@endphp
<x-adminlte4::layouts.app :title="($creator['display_name'] ?? __('web/vtual.profiles.manage_title')).' — '.__('web/vtual.title')">
    <x-adminlte4::card :title="$creator['display_name'] ?? __('web/vtual.profiles.manage_title')">
        @include('adminlte4.vtual.partials.nav')

        {{-- Profile metadata --}}
        <h5 class="mb-2">{{ __('web/vtual.profiles.section_profile') }}</h5>
        @if ($canMutate)
            <form method="POST" action="{{ route('vtual.profiles.update', $creatorId) }}" class="border rounded p-3 mb-4">
                @csrf
                @method('PATCH')
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('web/vtual.display_name') }}</label>
                        <input type="text" name="display_name" class="form-control form-control-sm"
                               value="{{ $creator['display_name'] ?? '' }}" required maxlength="120">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('web/vtual.slug') }}</label>
                        <input type="text" name="slug" class="form-control form-control-sm"
                               value="{{ $creator['slug'] ?? '' }}" maxlength="120">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('web/vtual.bio') }}</label>
                        <textarea name="bio" class="form-control form-control-sm" rows="3" maxlength="2000">{{ $creator['bio'] ?? '' }}</textarea>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">{{ __('web/vtual.avatar_url') }}</label>
                        <input type="text" name="avatar_url" class="form-control form-control-sm"
                               value="{{ $creator['avatar_url'] ?? '' }}" maxlength="2048">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('web/vtual.profiles.save_profile') }}</button>
                    </div>
                </div>
            </form>
        @else
            <dl class="row mb-4">
                <dt class="col-sm-2">{{ __('web/vtual.display_name') }}</dt>
                <dd class="col-sm-10">{{ $creator['display_name'] ?? '' }}</dd>
                <dt class="col-sm-2">Slug</dt>
                <dd class="col-sm-10"><code>{{ $creator['slug'] ?? '' }}</code></dd>
                <dt class="col-sm-2">{{ __('web/vtual.bio') }}</dt>
                <dd class="col-sm-10">{{ $creator['bio'] ?? '—' }}</dd>
            </dl>
        @endif

        {{-- Merge into another profile --}}
        @if (!empty($canMerge) && isset($mergeTargets) && $mergeTargets->isNotEmpty())
            <h5 class="mb-2">{{ __('web/vtual.profiles.section_merge') }}</h5>
            <form method="POST" action="{{ route('vtual.profiles.merge', $creatorId) }}"
                  class="border border-warning rounded p-3 mb-4"
                  onsubmit="return confirm(@json(__('web/vtual.profiles.merge_confirm')));">
                @csrf
                <p class="small text-muted mb-2">{{ __('web/vtual.profiles.merge_help') }}</p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">{{ __('web/vtual.profiles.merge_destination') }}</label>
                        <select name="destination_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('web/vtual.profiles.merge_pick') }}</option>
                            @foreach ($mergeTargets as $target)
                                <option value="{{ $target['id'] }}">
                                    {{ $target['display_name'] ?? $target['slug'] ?? $target['id'] }}
                                    @if (!empty($target['slug']))
                                        ({{ $target['slug'] }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-warning btn-sm w-100">
                            {{ __('web/vtual.profiles.merge_submit') }}
                        </button>
                    </div>
                </div>
            </form>
        @endif

        {{-- External links --}}
        <h5 class="mb-2">{{ __('web/vtual.profiles.section_links') }}</h5>
        @if ($canMutate)
            <form method="POST" action="{{ route('vtual.profiles.links.store', $creatorId) }}" class="border rounded p-3 mb-3">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('web/vtual.profiles.link_label') }}</label>
                        <input type="text" name="label" class="form-control form-control-sm" maxlength="120">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('web/vtual.url') }}</label>
                        <input type="text" name="url" class="form-control form-control-sm" required maxlength="2048" placeholder="https://">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('web/vtual.profiles.link_kind') }}</label>
                        <select name="kind" class="form-select form-select-sm">
                            @foreach ($kinds as $kind)
                                <option value="{{ $kind }}">{{ $kind }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('web/vtual.profiles.add_link') }}</button>
                    </div>
                </div>
            </form>
        @endif
        <div class="table-responsive mb-4">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.profiles.link_label') }}</th>
                        <th>{{ __('web/vtual.url') }}</th>
                        <th>{{ __('web/vtual.profiles.link_kind') }}</th>
                        @if ($canMutate)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($links as $link)
                        <tr>
                            <td>{{ $link['label'] ?? '—' }}</td>
                            <td class="small"><a href="{{ $link['url'] ?? '#' }}" target="_blank" rel="noopener">{{ $link['url'] ?? '' }}</a></td>
                            <td><span class="badge text-bg-light border">{{ $link['kind'] ?? 'other' }}</span></td>
                            @if ($canMutate)
                                <td class="text-end">
                                    <form method="POST" action="{{ route('vtual.profiles.links.destroy', [$creatorId, $link['id']]) }}" class="d-inline"
                                          onsubmit="return confirm('Delete this link?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('web/vtual.delete') }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canMutate ? 4 : 3 }}">
                                <x-adminlte4::empty-state icon="bi-link-45deg" :title="__('web/vtual.profiles.empty_links')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Channels (add = start bio claim, not instant bind) --}}
        <h5 class="mb-2">{{ __('web/vtual.profiles.section_channels') }}</h5>
        @if ($canMutate)
            <form method="POST" action="{{ route('vtual.profiles.channels.store', $creatorId) }}" class="border rounded p-3 mb-3">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('web/vtual.platform') }}</label>
                        <select name="platform_key" class="form-select form-select-sm" required>
                            @foreach ($platforms as $platform)
                                @if (!empty($platform['enabled']))
                                    <option value="{{ $platform['key'] }}">{{ $platform['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('web/vtual.profile') }}</label>
                        <input type="text" name="profile" class="form-control form-control-sm font-monospace"
                               required maxlength="512" placeholder="UC... / @handle / login / URL">
                        <div class="form-text">{{ __('web/vtual.profiles.channel_claim_start_help') }}</div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('web/vtual.add_channel') }}</button>
                    </div>
                </div>
            </form>
        @endif

        {{-- Pending channel claims --}}
        <h6 class="mb-2">{{ __('web/vtual.profiles.section_channel_claims') }}</h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.platform') }}</th>
                        <th>{{ __('web/vtual.external_id') }}</th>
                        <th>{{ __('web/vtual.handle') }}</th>
                        <th>{{ __('web/vtual.profiles.claim_token') }}</th>
                        <th>{{ __('web/vtual.profiles.claim_expires') }}</th>
                        @if ($canMutate)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($channelClaims as $claim)
                        <tr>
                            <td>{{ $claim['platform']['name'] ?? $claim['platform']['key'] ?? '' }}</td>
                            <td><code>{{ $claim['external_id'] ?? '' }}</code></td>
                            <td>{{ $claim['handle'] ?? '-' }}</td>
                            <td>
                                <code class="user-select-all">{{ $claim['token'] ?? '' }}</code>
                                <div class="form-text">{{ __('web/vtual.profiles.claim_token_help') }}</div>
                            </td>
                            <td class="small text-muted">{{ $claim['expires_at'] ?? '-' }}</td>
                            @if ($canMutate)
                                <td class="text-end text-nowrap">
                                    <form method="POST"
                                          action="{{ route('vtual.profiles.channel-claims.verify', [$creatorId, $claim['id']]) }}"
                                          class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            {{ __('web/vtual.profiles.verify_channel') }}
                                        </button>
                                    </form>
                                    <form method="POST"
                                          action="{{ route('vtual.profiles.channel-claims.cancel', [$creatorId, $claim['id']]) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Cancel this channel claim?');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                                            {{ __('web/vtual.profiles.cancel_channel_claim') }}
                                        </button>
                                    </form>
                                    @if (!empty($canAcceptAnyway))
                                        <form method="POST"
                                              action="{{ route('vtual.profiles.channel-claims.accept-anyway', [$creatorId, $claim['id']]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Accept without bio check?');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                                {{ __('web/vtual.claims.accept_anyway') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canMutate ? 6 : 5 }}">
                                <span class="text-muted small">{{ __('web/vtual.profiles.empty_channel_claims') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h6 class="mb-2">{{ __('web/vtual.profiles.section_active_channels') }}</h6>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th></th>
                        <th>{{ __('web/vtual.platform') }}</th>
                        <th>{{ __('web/vtual.external_id') }}</th>
                        <th>{{ __('web/vtual.handle') }}</th>
                        <th>{{ __('web/vtual.bio') }}</th>
                        <th>{{ __('web/vtual.status') }}</th>
                        @if ($canMutate)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($channels as $channel)
                        @php
                            $desc = (string) ($channel['description'] ?? '');
                            $descShort = $desc === '' ? '—' : \Illuminate\Support\Str::limit($desc, 80);
                        @endphp
                        <tr>
                            <td class="text-nowrap" style="width: 3rem;">
                                @if (! empty($channel['avatar_url']))
                                    <img src="{{ $channel['avatar_url'] }}" alt=""
                                         class="rounded-circle" width="36" height="36"
                                         style="object-fit: cover;" loading="lazy"
                                         referrerpolicy="no-referrer">
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>{{ $channel['platform']['name'] ?? $channel['platform']['key'] ?? '' }}</td>
                            <td><code>{{ $channel['external_id'] ?? '' }}</code></td>
                            <td>{{ $channel['handle'] ?? '—' }}</td>
                            <td class="small text-muted" title="{{ $desc }}">{{ $descShort }}</td>
                            <td><span class="badge text-bg-light border">{{ $channel['status'] ?? '' }}</span></td>
                            @if ($canMutate)
                                <td class="text-end text-nowrap">
                                    <form method="POST"
                                          action="{{ route('vtual.profiles.channels.refresh', [$creatorId, $channel['id']]) }}"
                                          class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                                            {{ __('web/vtual.refresh_channel') }}
                                        </button>
                                    </form>
                                    <form method="POST"
                                          action="{{ route('vtual.profiles.channels.archive', [$creatorId, $channel['id']]) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Archive this channel?');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            {{ __('web/vtual.archive_channel') }}
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canMutate ? 7 : 6 }}">
                                <x-adminlte4::empty-state icon="bi-broadcast" :title="__('web/vtual.empty_channels')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Content stub --}}
        <h5 class="mb-2">{{ __('web/vtual.profiles.section_content') }}</h5>
        <p class="text-muted small mb-0">{{ __('web/vtual.profiles.content_stub') }}</p>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
