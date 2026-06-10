<x-layouts.app title="Ubah Pengguna">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Pengguna</a></li>
        <li class="breadcrumb-item active" aria-current="page">Ubah</li>
    </x-slot:breadcrumb>

    <div class="row">
        <div class="col-lg-6">
            <x-card title="Ubah data: {{ $user->name }}">
                @include('users._form', [
                    'action' => route('users.update', $user),
                    'method' => 'PUT',
                    'isEdit' => true,
                    'submitLabel' => 'Perbarui',
                ])
            </x-card>
        </div>
    </div>
</x-layouts.app>
