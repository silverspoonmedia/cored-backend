@php
    $isEdit = (bool) ($isEdit ?? false);
    $cardData = $card ?? [];
@endphp

<x-adminlte4::form.input
    name="slug"
    :label="__('web/inference.model_cards.fields.slug')"
    :value="old('slug', $cardData['slug'] ?? null)"
    :readonly="$isEdit"
    required
/>
<x-adminlte4::form.input
    name="display_name"
    :label="__('web/inference.model_cards.fields.display_name')"
    :value="old('display_name', $cardData['display_name'] ?? null)"
/>
<div class="mb-3">
    <div class="form-check form-switch">
        <input
            class="form-check-input"
            type="checkbox"
            name="is_active"
            id="is_active"
            value="1"
            @checked(old('is_active', $cardData['is_active'] ?? true))
        >
        <label class="form-check-label" for="is_active">{{ __('web/inference.model_cards.fields.is_active') }}</label>
    </div>
    <x-adminlte4::form.error name="is_active" />
</div>
