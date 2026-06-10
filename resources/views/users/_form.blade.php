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
    <x-form.input name="name" label="Nama" :value="$user->name ?? null" required />
    <x-form.input name="email" type="email" label="Email" :value="$user->email ?? null" required />

    <x-form.input
        name="password"
        type="password"
        label="Kata sandi"
        :required="! $isEdit"
        autocomplete="new-password"
        :hint="$isEdit ? 'Kosongkan jika tidak ingin mengubah kata sandi.' : 'Minimal 8 karakter.'"
    />
    <x-form.input
        name="password_confirmation"
        type="password"
        label="Konfirmasi kata sandi"
        :required="! $isEdit"
        autocomplete="new-password"
    />

    <x-form.select
        name="role"
        label="Role"
        :options="$roleOptions"
        :selected="$currentRole ?? null"
        placeholder="Pilih role"
        required
    />

    <div class="d-flex gap-2">
        <x-form.submit :label="$submitLabel" class="btn btn-primary" />
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</x-form.ajax>
