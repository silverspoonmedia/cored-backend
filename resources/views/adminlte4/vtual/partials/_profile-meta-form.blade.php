@php
    $creatorId = $creator['id'] ?? '';
@endphp
<h5 class="mb-2">{{ __('web/vtual.profiles.section_profile') }}</h5>
@if ($canMutate)
    <x-adminlte4::form.ajax :action="route('vtual.profiles.update', $creatorId)" method="PATCH" class="border rounded p-3 mb-4">
        <div class="row g-2">
            <div class="col-md-6" data-form-field="display_name">
                <label class="form-label" for="display_name">{{ __('web/vtual.display_name') }}</label>
                <input type="text" id="display_name" name="display_name" class="form-control form-control-sm"
                       value="{{ $creator['display_name'] ?? '' }}" required maxlength="120">
                <x-adminlte4::form.error name="display_name" />
            </div>
            <div class="col-md-6" data-form-field="slug">
                <label class="form-label" for="slug">{{ __('web/vtual.slug') }}</label>
                <input type="text" id="slug" name="slug" class="form-control form-control-sm"
                       value="{{ $creator['slug'] ?? '' }}" maxlength="120">
                <x-adminlte4::form.error name="slug" />
            </div>
            <div class="col-12" data-form-field="bio">
                <label class="form-label" for="bio">{{ __('web/vtual.bio') }}</label>
                <textarea id="bio" name="bio" class="form-control form-control-sm" rows="3" maxlength="2000">{{ $creator['bio'] ?? '' }}</textarea>
                <x-adminlte4::form.error name="bio" />
            </div>
            <div class="col-md-8" data-form-field="avatar_url">
                <label class="form-label" for="avatar_url">{{ __('web/vtual.avatar_url') }}</label>
                <input type="text" id="avatar_url" name="avatar_url" class="form-control form-control-sm"
                       value="{{ $creator['avatar_url'] ?? '' }}" maxlength="2048">
                <x-adminlte4::form.error name="avatar_url" />
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <x-adminlte4::form.submit :label="__('web/vtual.profiles.save_profile')" class="btn btn-primary btn-sm w-100" />
            </div>
        </div>
    </x-adminlte4::form.ajax>
@else
    <dl class="row mb-4">
        <dt class="col-sm-2">{{ __('web/vtual.display_name') }}</dt>
        <dd class="col-sm-10">{{ $creator['display_name'] ?? '' }}</dd>
        <dt class="col-sm-2">{{ __('web/vtual.slug') }}</dt>
        <dd class="col-sm-10"><code>{{ $creator['slug'] ?? '' }}</code></dd>
        <dt class="col-sm-2">{{ __('web/vtual.bio') }}</dt>
        <dd class="col-sm-10">{{ $creator['bio'] ?? '—' }}</dd>
    </dl>
@endif
