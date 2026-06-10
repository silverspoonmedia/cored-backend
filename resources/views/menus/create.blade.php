<x-layouts.app title="Tambah Menu">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('menus.index') }}">Menu</a></li>
        <li class="breadcrumb-item active" aria-current="page">Tambah</li>
    </x-slot:breadcrumb>

    <x-card title="Data menu baru">
        @include('menus._form', [
            'action' => route('menus.store'),
            'method' => 'POST',
            'isEdit' => false,
            'menu' => null,
            'submitLabel' => 'Simpan',
        ])
    </x-card>
</x-layouts.app>
