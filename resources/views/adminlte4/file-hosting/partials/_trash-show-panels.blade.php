        {{-- SPA trash panel (soft-rendered; empty shell on cold load of index) --}}
        <div data-fh-panel="trash" class="d-none" hidden>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">{{ __('web/file-hosting.trash_title') }}</h5>
                <a href="{{ route('file-hosting.index', $ownerQuery) }}" class="btn btn-outline-secondary btn-sm" data-fh-soft-nav>
                    <i class="bi bi-arrow-left me-1"></i> {{ __('web/file-hosting.back') }}
                </a>
            </div>
            <div class="table-responsive">
                <table class="table fh-table">
                    <thead>
                        <tr>
                            <th class="fh-table__icon-cell"></th>
                            <th>{{ __('web/file-hosting.columns.name') }}</th>
                            <th>{{ __('web/file-hosting.columns.type') }}</th>
                            <th>{{ __('web/file-hosting.columns.deleted_at') }}</th>
                            <th class="text-end">{{ __('web/file-hosting.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-fh-trash-body>
                        <tr>
                            <td colspan="5">
                                <div class="fh-empty">
                                    <i class="bi bi-trash3 fh-empty__icon"></i>
                                    <div class="fh-empty__text">{{ __('web/file-hosting.trash_empty') }}</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SPA show panel (file detail soft-rendered) --}}
        <div data-fh-panel="show" class="d-none" hidden></div>
    </div>
