<x-adminlte4::layouts.app :title="__('web/menus.title')">
    <x-adminlte4::card :title="__('web/menus.list_title')">
        @can('manage menu')
            <x-slot:actions>
                <a href="{{ route('menus.trashed', ['layout' => $selectedLayout]) }}" class="btn btn-outline-secondary btn-sm me-1">
                    <i class="bi bi-trash me-1"></i> {{ __('web/menus.trashed_breadcrumb') }}
                </a>
                <a href="{{ route('menus.create', ['layout' => $selectedLayout]) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('web/menus.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <form method="GET" action="{{ route('menus.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <x-adminlte4::form.select
                    name="layout"
                    :label="__('web/menus.filter.layout_label')"
                    :options="$layoutOptions"
                    :selected="$selectedLayout"
                    :hint="__('web/menus.filter.layout_hint')"
                    :autosubmit="true"
                />
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/menus.columns.name') }}</th>
                        <th>{{ __('web/menus.columns.type') }}</th>
                        <th>{{ __('web/menus.columns.route') }}</th>
                        <th>{{ __('web/menus.columns.order') }}</th>
                        <th>{{ __('web/menus.columns.attributes') }}</th>
                        <th class="text-end">{{ __('web/menus.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menus as $menu)
                        @php($hasChildren = ($menu['children_count'] ?? 0) > 0)
                        <tr>
                            <td>
                                <span @class([
                                    'd-inline-block',
                                    'ps-4' => $menu['type'] === 'c',
                                    'ps-3' => $menu['type'] === 'p',
                                ])>
                                    @if ($menu['icon'])<i class="bi {{ $menu['icon'] }} me-1" aria-hidden="true"></i>@endif
                                    <span @class(['fw-bold' => $hasChildren])>{{ $menu['name'] }}</span>
                                    @if ($hasChildren)
                                        <span class="badge text-bg-light border text-muted ms-1 fw-normal"
                                            title="{{ __('web/menus.has_children_title', ['count' => $menu['children_count']]) }}">
                                            <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>{{ $menu['children_count'] }}
                                        </span>
                                    @endif
                                </span>
                            </td>
                            <td><code>{{ $menu['type'] }}</code></td>
                            <td>{{ $menu['route'] ?? '—' }}</td>
                            <td>{{ $menu['order'] ?? '—' }}</td>
                            <td>
                                @if ($menu['is_authenticate'])<span class="badge text-bg-info">auth</span>@endif
                                @if ($menu['is_guest_only'])<span class="badge text-bg-secondary">guest</span>@endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group" aria-label="{{ __('ui.labels.actions') }}">
                                    @can('manage menu')
                                        <a href="{{ route('menus.edit', $menu['id']) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('web/menus.edit_title') }}" title="{{ __('web/menus.edit_title') }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </a>
                                        <x-adminlte4::form.ajax
                                            :action="route('menus.destroy', ['menu' => $menu['id'], 'layout' => $selectedLayout])"
                                            method="DELETE"
                                            class="d-inline"
                                        >
                                            <button type="button"
                                                    class="btn btn-sm btn-danger"
                                                    title="{{ __('web/menus.delete_title') }}"
                                                    aria-label="{{ __('web/menus.delete_title') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#menuDeleteConfirmModal"
                                                    data-menu-name="{{ $menu['name'] }}"
                                                    data-menu-cascade-url="{{ route('menus.destroy', ['menu' => $menu['id'], 'layout' => $selectedLayout, 'cascade' => 1]) }}"
                                                    data-menu-has-children="{{ $hasChildren ? '1' : '0' }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                <span class="spinner-border spinner-border-sm d-none ms-1" data-form-submit-spinner role="status" aria-hidden="true"></span>
                                            </button>
                                        </x-adminlte4::form.ajax>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-adminlte4::empty-state icon="bi-list-nested" :title="__('web/menus.empty_list_layout', ['layout' => $layoutOptions[$selectedLayout] ?? $selectedLayout])" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="modal fade" id="menuDeleteConfirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="menuDeleteConfirmLabel">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="menuDeleteConfirmLabel">{{ __('web/menus.delete_confirm_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p data-menu-confirm-text data-menu-confirm-template="{{ __('web/menus.delete_confirm_question', ['name' => '__NAME__']) }}"></p>
                        <div class="form-check mt-3">
                            <input type="checkbox" class="form-check-input" id="menuCascadeCheck">
                            <label class="form-check-label" for="menuCascadeCheck">{{ __('web/menus.delete_cascade_option') }}</label>
                        </div>
                        <div class="form-text">{{ __('web/menus.delete_cascade_hint') }}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.actions.cancel') }}</button>
                        <button type="button" class="btn btn-danger" data-menu-confirm-submit>
                            <span class="spinner-border spinner-border-sm d-none" data-form-submit-spinner role="status" aria-hidden="true"></span>
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('web/menus.delete_title') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
    <script>
        (function () {
            'use strict';

            const modalEl = document.getElementById('menuDeleteConfirmModal');
            if (!modalEl) {
                return;
            }

            const cascadeCheck = modalEl.querySelector('#menuCascadeCheck');
            const confirmText = modalEl.querySelector('[data-menu-confirm-text]');
            const submitBtn = modalEl.querySelector('[data-menu-confirm-submit]');
            const cascadeHint = modalEl.querySelector('.form-text');

            let activeForm = null;
            let cascadeUrl = '';
            let defaultUrl = '';

            modalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                if (!trigger) {
                    return;
                }

                const menuName = trigger.dataset.menuName || '';
                cascadeUrl = trigger.dataset.menuCascadeUrl || '';
                const hasChildren = trigger.dataset.menuHasChildren === '1';

                activeForm = trigger.closest('form[data-ajax-form]');
                defaultUrl = activeForm ? activeForm.getAttribute('action') : '';

                const template = confirmText.dataset.menuConfirmTemplate || '';
                confirmText.textContent = template.replace('__NAME__', menuName);

                cascadeCheck.checked = hasChildren;
                cascadeCheck.disabled = false;
                cascadeHint.classList.remove('text-danger');
                if (hasChildren) {
                    cascadeHint.classList.add('text-danger');
                }
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                activeForm = null;
                cascadeUrl = '';
                defaultUrl = '';
                cascadeCheck.checked = false;
            });

            cascadeCheck.addEventListener('change', function () {
                if (!activeForm) {
                    return;
                }
                activeForm.setAttribute('action', cascadeCheck.checked ? cascadeUrl : defaultUrl);
            });

            submitBtn.addEventListener('click', function () {
                if (!activeForm) {
                    return;
                }

                if (cascadeCheck.checked && cascadeUrl) {
                    activeForm.setAttribute('action', cascadeUrl);
                } else {
                    activeForm.setAttribute('action', defaultUrl);
                }

                const modal = window.bootstrap?.Modal?.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }

                activeForm.requestSubmit();
            });
        })();
    </script>
    </x-slot:scripts>
</x-adminlte4::layouts.app>
