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

<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

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
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
