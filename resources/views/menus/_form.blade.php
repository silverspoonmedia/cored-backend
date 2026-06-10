{{--
    Shared menu create/edit form.
    Expected variables:
      $action, $method ('POST'|'PUT'), $isEdit (bool), $submitLabel
      $menu     Menu|null (edit only, with roles/includedUsers/excludedUsers loaded)
      $allMenus Collection<Menu> (parent / reference candidates)
      $roles    Collection<Role>
      $users    Collection<User>
--}}
@php
    $typeOptions = ['h' => 'Header (h)', 'p' => 'Parent (p)', 'c' => 'Child (c)'];
    $parentOptions = $allMenus->mapWithKeys(fn ($m) => [$m->id => $m->name.' · '.$m->type]);
    $roleOptions = $roles->pluck('name', 'id');
    $userOptions = $users->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')']);

    $positionOptions = $isEdit
        ? ['before' => 'Sebelum referensi', 'after' => 'Sesudah referensi']
        : ['append' => 'Di akhir grup', 'before' => 'Sebelum referensi', 'after' => 'Sesudah referensi'];
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div class="row">
        <div class="col-md-6">
            <x-form.input name="name" label="Nama" :value="$menu->name ?? null" required />
        </div>
        <div class="col-md-6">
            <x-form.select name="type" label="Tipe" :options="$typeOptions" :selected="$menu->type ?? null"
                placeholder="Pilih tipe" required
                hint="h harus root, p berada di bawah h, c berada di bawah p." />
        </div>
    </div>

    <x-form.select name="parent" label="Induk (parent)" :options="$parentOptions" :selected="$menu->parent_id ?? null"
        placeholder="Tidak ada (root)" />

    <div class="row">
        <div class="col-md-6">
            <x-form.input name="route" label="Route" :value="$menu->route ?? null"
                hint="Nama route Laravel yang terdaftar (mis. dashboard), bukan URL." />
        </div>
        <div class="col-md-6">
            <x-form.input name="icon" label="Ikon" :value="$menu->icon ?? null"
                hint="Kelas ikon lengkap, mis. bi bi-house." />
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-form.checkbox name="authenticate" label="Hanya untuk pengguna terautentikasi"
                :checked="(bool) ($menu->is_authenticate ?? false)" />
        </div>
        <div class="col-md-6">
            <x-form.checkbox name="guest_only" label="Hanya untuk tamu (guest)"
                :checked="(bool) ($menu->is_guest_only ?? false)" />
        </div>
    </div>

    <x-form.multiselect name="roles" label="Role yang boleh melihat" :options="$roleOptions"
        :selected="$menu?->roles->pluck('id')->all() ?? []"
        hint="Kosongkan agar terlihat oleh semua yang lolos aturan lain. Tidak kompatibel dengan 'guest only'." />

    <div class="row">
        <div class="col-md-6">
            <x-form.multiselect name="user_includes" label="Pengguna disertakan (allowlist)" :options="$userOptions"
                :selected="$menu?->includedUsers->pluck('id')->all() ?? []" />
        </div>
        <div class="col-md-6">
            <x-form.multiselect name="user_excludes" label="Pengguna dikecualikan (blacklist)" :options="$userOptions"
                :selected="$menu?->excludedUsers->pluck('id')->all() ?? []" />
        </div>
    </div>

    <hr />
    <p class="text-muted small mb-2">Penempatan (opsional)</p>
    <div class="row">
        <div class="col-md-6">
            <x-form.select name="position" label="Posisi" :options="$positionOptions" placeholder="Default (akhir grup)" />
        </div>
        <div class="col-md-6">
            <x-form.select name="reference_id" label="Menu referensi" :options="$parentOptions"
                placeholder="Pilih menu referensi"
                hint="Wajib jika posisi 'sebelum' atau 'sesudah'." />
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('menus.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
