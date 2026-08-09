@php
    $creatorRow = is_array($creator ?? null) ? $creator : [];
@endphp

<x-adminlte4::form.ajax :action="$action" :method="$method">
    <x-adminlte4::form.input
        name="display_name"
        :label="__('web/vtual-orphans.form.display_name')"
        :value="$creatorRow['display_name'] ?? null"
        required
    />
    @if ($isEdit ?? false)
        <x-adminlte4::form.input
            name="slug"
            :label="__('web/vtual-orphans.form.slug')"
            :value="$creatorRow['slug'] ?? null"
            :hint="__('web/vtual-orphans.form.slug_hint')"
        />
    @endif
    <x-adminlte4::form.input
        name="bio"
        :label="__('web/vtual-orphans.form.bio')"
        :value="$creatorRow['bio'] ?? null"
    />
    <x-adminlte4::form.input
        name="avatar_url"
        type="url"
        :label="__('web/vtual-orphans.form.avatar_url')"
        :value="$creatorRow['avatar_url'] ?? null"
    />

    <div class="d-flex gap-2">
        <x-adminlte4::form.submit :label="$submitLabel" class="btn btn-primary" />
        <a href="{{ route('vtual.orphans.index') }}" class="btn btn-outline-secondary">{{ __('ui.actions.cancel') }}</a>
    </div>
</x-adminlte4::form.ajax>
