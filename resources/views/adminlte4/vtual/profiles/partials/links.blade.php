@php
    $routeKey = $creatorRouteKey ?? $creator['slug'] ?? $creator['id'] ?? '';
    $kinds = ['social', 'donation', 'website', 'other'];
@endphp
<h5 class="mb-2">{{ __('web/vtual.profiles.section_links') }}</h5>
@if ($canMutate)
    <x-adminlte4::form.ajax :action="route('vtual.profiles.links.store', $routeKey)" method="POST" class="border rounded p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3" data-form-field="label">
                <label class="form-label" for="link_label">{{ __('web/vtual.profiles.link_label') }}</label>
                <input type="text" id="link_label" name="label" class="form-control form-control-sm" maxlength="120">
                <x-adminlte4::form.error name="label" />
            </div>
            <div class="col-md-4" data-form-field="url">
                <label class="form-label" for="link_url">{{ __('web/vtual.url') }}</label>
                <input type="text" id="link_url" name="url" class="form-control form-control-sm" required maxlength="2048" placeholder="https://">
                <x-adminlte4::form.error name="url" />
            </div>
            <div class="col-md-3" data-form-field="kind">
                <label class="form-label" for="link_kind">{{ __('web/vtual.profiles.link_kind') }}</label>
                <select id="link_kind" name="kind" class="form-select form-select-sm">
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind }}">{{ $kind }}</option>
                    @endforeach
                </select>
                <x-adminlte4::form.error name="kind" />
            </div>
            <div class="col-md-2">
                <x-adminlte4::form.submit :label="__('web/vtual.profiles.add_link')" class="btn btn-primary btn-sm w-100" />
            </div>
        </div>
    </x-adminlte4::form.ajax>
@endif
@php
    $linkActionLabels = [
        'delete' => __('web/vtual.delete'),
        'confirm' => [
            'delete_title' => __('web/vtual.confirm.delete_link_title'),
            'delete_body' => __('web/vtual.confirm.delete_link_body'),
            'delete_submit' => __('web/vtual.delete'),
        ],
    ];
@endphp
<div class="table-responsive mb-4" id="profile-links-table" data-link-action-labels='@json($linkActionLabels)'>
    <table class="table table-sm table-hover align-middle mb-0" aria-label="{{ __('web/vtual.table.caption_links') }}">
        <caption class="visually-hidden">{{ __('web/vtual.table.caption_links') }}</caption>
        <thead>
            <tr>
                <th>{{ __('web/vtual.profiles.link_label') }}</th>
                <th>{{ __('web/vtual.url') }}</th>
                <th>{{ __('web/vtual.profiles.link_kind') }}</th>
                @if ($canMutate)<th class="text-end">{{ __('web/vtual.table.label_actions') }}</th>@endif
            </tr>
        </thead>
        <tbody id="profile-links-tbody">
            @forelse ($links as $link)
                <tr>
                    <td>{{ $link['label'] ?? '—' }}</td>
                    <td class="small"><a href="{{ $link['url'] ?? '#' }}" target="_blank" rel="noopener">{{ $link['url'] ?? '' }}</a></td>
                    <td><span class="badge text-bg-light border">{{ $link['kind'] ?? 'other' }}</span></td>
                    @if ($canMutate)
                        <td class="text-end">
                            <x-adminlte4::form.ajax :action="route('vtual.profiles.links.destroy', [$routeKey, $link['id']])" method="DELETE" class="d-inline">
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#vtualConfirmModal"
                                        data-confirm-title="{{ __('web/vtual.confirm.delete_link_title') }}"
                                        data-confirm-body="{{ __('web/vtual.confirm.delete_link_body') }}"
                                        data-confirm-submit-label="{{ __('web/vtual.delete') }}"
                                        data-confirm-submit-class="btn-danger"
                                        aria-label="{{ __('web/vtual.delete') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </x-adminlte4::form.ajax>
                        </td>
                    @endif
                </tr>
            @empty
                <tr id="profile-links-empty" data-empty-row>
                    <td colspan="{{ $canMutate ? 4 : 3 }}">
                        <x-adminlte4::empty-state icon="bi-link-45deg" :title="__('web/vtual.profiles.empty_links')" />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
