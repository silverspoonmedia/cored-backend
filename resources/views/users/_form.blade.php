{{--
    Shared user create/edit form.
    Expected variables:
      $action        route URL
      $method        'POST' | 'PUT'
      $isEdit        bool
      $assignableRoles  Collection<Role> the actor may assign
      $user          User|null (edit only)
      $currentRole   string|null (edit only)
      $submitLabel   string
--}}
@php($roleOptions = $assignableRoles->pluck('name', 'name'))

<x-form.ajax :action="$action" :method="$method">
    <x-form.input name="name" :label="__('ui.labels.name')" :value="$user->name ?? null" required />
    <x-form.input name="email" type="email" :label="__('ui.labels.email')" :value="$user->email ?? null" required />

    <x-form.input
        name="password"
        type="password"
        :label="__('ui.labels.password')"
        :required="! $isEdit"
        autocomplete="new-password"
        :hint="$isEdit ? __('web/users.form.password_hint_edit') : __('web/users.form.password_hint_create')"
    />
    <x-form.input
        name="password_confirmation"
        type="password"
        :label="__('web/users.form.password_confirm')"
        :required="! $isEdit"
        autocomplete="new-password"
    />

    <x-form.select
        name="role"
        :label="__('ui.labels.role')"
        :options="$roleOptions"
        :selected="$currentRole ?? null"
        :placeholder="__('web/users.form.role_placeholder')"
        required
    />

    <div class="d-flex gap-2">
        <x-form.submit :label="$submitLabel" class="btn btn-primary" />
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">{{ __('ui.actions.cancel') }}</a>
    </div>
</x-form.ajax>
