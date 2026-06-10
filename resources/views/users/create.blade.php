<x-layouts.app title="Tambah Pengguna">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Pengguna</a></li>
        <li class="breadcrumb-item active" aria-current="page">Tambah</li>
    </x-slot:breadcrumb>

    <div class="row">
        <div class="col-lg-6">
            <x-card title="Data pengguna baru">
                @include('users._form', [
                    'action' => route('users.store'),
                    'method' => 'POST',
                    'isEdit' => false,
                    'user' => null,
                    'currentRole' => null,
                    'submitLabel' => 'Simpan',
                ])
            </x-card>
        </div>
    </div>
</x-layouts.app>
