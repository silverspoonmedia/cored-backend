{{--
    Shared menu create/edit form.
--}}
@php
    use App\Support\Navigation\MenuLayout;

    $typeOptions = [
        'h' => __('web/menus.form.types.h'),
        'p' => __('web/menus.form.types.p'),
        'c' => __('web/menus.form.types.c'),
    ];
    $parentOptions = $allMenus->mapWithKeys(fn ($m) => [$m['id'] => $m['name'].' · '.$m['type']]);
    $roleOptions = $roles->pluck('name', 'id');
    $userOptions = $users->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')']);

    $positionOptions = $isEdit
        ? [
            'before' => __('web/menus.form.position.before'),
            'after' => __('web/menus.form.position.after'),
        ]
        : [
            'append' => __('web/menus.form.position.append'),
            'before' => __('web/menus.form.position.before'),
            'after' => __('web/menus.form.position.after'),
        ];

    $nameTranslations = data_get($menu, 'name_translations', []);
    $descriptionTranslations = data_get($menu, 'description_translations', []);
    $selectedLayout = data_get($menu, 'layout', MenuLayout::default());
    $layoutOptions = collect(MenuLayout::all())->mapWithKeys(
        fn (string $layout) => [$layout => __('web/menus.form.layouts.'.$layout)],
    );
@endphp

<x-adminlte4::form.ajax :action="$action" :method="$method">
    <div class="row">
        <div class="col-md-6">
            <x-adminlte4::form.translatable-input
                name="name"
                :label="__('ui.labels.name')"
                :translations="$nameTranslations"
                :hint="__('web/menus.form.name_hint')"
                required
            />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.select name="type" :label="__('web/menus.columns.type')" :options="$typeOptions" :selected="data_get($menu, 'type')"
                :placeholder="__('web/menus.form.type_placeholder')" required
                :hint="__('web/menus.form.type_hint')" />
        </div>
    </div>

    @if ($isEdit)
        <x-adminlte4::form.input name="layout" :label="__('web/menus.columns.layout')" :value="$selectedLayout" readonly
            :hint="__('web/menus.form.layout_readonly_hint')" />
    @else
        <x-adminlte4::form.select name="layout" :label="__('web/menus.columns.layout')" :options="$layoutOptions" :selected="$selectedLayout"
            required :hint="__('web/menus.form.layout_hint')" />
    @endif

    <x-adminlte4::form.translatable-input
        name="description"
        :label="__('web/menus.form.description_label')"
        :translations="$descriptionTranslations"
        :hint="__('web/menus.form.description_hint')"
    />

    <x-adminlte4::form.select name="parent" :label="__('web/menus.form.parent_label')" :options="$parentOptions" :selected="data_get($menu, 'parent_id')"
        :placeholder="__('web/menus.form.parent_placeholder')" />

    <div class="row">
        <div class="col-md-6">
            <x-adminlte4::form.input name="route" :label="__('web/menus.columns.route')" :value="data_get($menu, 'route')"
                :hint="__('web/menus.form.route_hint')" />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.input name="icon" :label="__('web/menus.form.icon_label')" :value="data_get($menu, 'icon')"
                :hint="__('web/menus.form.icon_hint')" />
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-adminlte4::form.checkbox name="authenticate" :label="__('web/menus.form.authenticate')"
                :checked="(bool) data_get($menu, 'is_authenticate', false)" />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.checkbox name="guest_only" :label="__('web/menus.form.guest_only')"
                :checked="(bool) data_get($menu, 'is_guest_only', false)" />
        </div>
    </div>

    <x-adminlte4::form.multiselect name="roles" :label="__('web/menus.form.roles_label')" :options="$roleOptions"
        :selected="data_get($menu, 'role_ids', [])"
        :hint="__('web/menus.form.roles_hint')" />

    <div class="row">
        <div class="col-md-6">
            <x-adminlte4::form.multiselect name="user_includes" :label="__('web/menus.form.user_includes')" :options="$userOptions"
                :selected="data_get($menu, 'user_include_ids', [])" />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.multiselect name="user_excludes" :label="__('web/menus.form.user_excludes')" :options="$userOptions"
                :selected="data_get($menu, 'user_exclude_ids', [])" />
        </div>
    </div>

    <hr />
    <p class="text-muted small mb-2">{{ __('web/menus.form.placement_heading') }}</p>
    <div class="row">
        <div class="col-md-6">
            <x-adminlte4::form.select name="position" :label="__('web/menus.form.position_label')" :options="$positionOptions"
                :placeholder="$isEdit ? __('web/menus.form.position_no_change') : __('web/menus.form.position_default')" />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.select name="reference_id" :label="__('web/menus.form.reference_label')" :options="$parentOptions"
                :placeholder="__('web/menus.form.reference_placeholder')"
                :hint="$isEdit ? __('web/menus.form.reference_hint_edit') : __('web/menus.form.reference_hint_create')" />
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <x-adminlte4::form.submit :label="$submitLabel" class="btn btn-primary" />
        <a href="{{ route('menus.index', ['layout' => $listLayout ?? data_get($menu, 'layout', MenuLayout::default())]) }}" class="btn btn-outline-secondary">{{ __('ui.actions.cancel') }}</a>
    </div>
</x-adminlte4::form.ajax>
