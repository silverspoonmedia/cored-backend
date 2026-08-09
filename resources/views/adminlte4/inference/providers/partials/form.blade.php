<x-adminlte4::form.input name="slug" :label="__('web/inference.providers.fields.slug')" :value="old('slug', $provider['slug'] ?? null)" :readonly="$isEdit ?? false" required />
<x-adminlte4::form.input name="name" :label="__('web/inference.providers.fields.name')" :value="old('name', $provider['name'] ?? null)" required />
<div class="mb-3">
    <div class="form-check form-switch">
        <input
            class="form-check-input"
            type="checkbox"
            name="is_active"
            id="provider_is_active"
            value="1"
            @checked(old('is_active', $provider['is_active'] ?? true))
        >
        <label class="form-check-label" for="provider_is_active">{{ __('web/inference.providers.fields.is_active') }}</label>
    </div>
    <x-adminlte4::form.error name="is_active" />
</div>
