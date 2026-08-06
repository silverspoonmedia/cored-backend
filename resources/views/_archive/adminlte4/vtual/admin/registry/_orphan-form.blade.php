<div class="card shadow-sm mb-4">
    <div class="card-header bg-body d-flex align-items-center gap-2">
        <span class="vtual-admin__intro-icon" aria-hidden="true">
            <i class="bi bi-person-plus"></i>
        </span>
        <div>
            <h2 class="h6 mb-0">{{ __('web/vtual.claims.orphan_create') }}</h2>
            <p class="text-muted small mb-0">{{ __('web/vtual.claims.orphan_help') }}</p>
        </div>
    </div>
    <div class="card-body">
        <x-adminlte4::form.ajax :action="route('vtual.admin.registry.orphan')" method="POST" class="row g-3">
            <div class="col-md-4" data-form-field="display_name">
                <label class="form-label" for="orphan_display_name">{{ __('web/vtual.display_name') }}</label>
                <input type="text" id="orphan_display_name" name="display_name" class="form-control form-control-sm" required maxlength="120">
                <x-adminlte4::form.error name="display_name" />
            </div>
            <div class="col-md-3" data-form-field="slug">
                <label class="form-label" for="orphan_slug">{{ __('web/vtual.slug') }}</label>
                <input type="text" id="orphan_slug" name="slug" class="form-control form-control-sm" maxlength="120">
                <x-adminlte4::form.error name="slug" />
            </div>
            <div class="col-md-5" data-form-field="bio">
                <label class="form-label" for="orphan_bio">{{ __('web/vtual.bio') }}</label>
                <input type="text" id="orphan_bio" name="bio" class="form-control form-control-sm" maxlength="500">
                <x-adminlte4::form.error name="bio" />
            </div>
            <div class="col-12">
                <x-adminlte4::form.submit :label="__('web/vtual.create')" class="btn btn-warning btn-sm" />
            </div>
        </x-adminlte4::form.ajax>
    </div>
</div>
