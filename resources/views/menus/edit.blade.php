<x-layouts.app title="Ubah Menu">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('menus.index') }}">Menu</a></li>
        <li class="breadcrumb-item active" aria-current="page">Ubah</li>
    </x-slot:breadcrumb>

    <x-card title="Ubah menu: {{ $menu->name }}">
        @include('menus._form', [
            'action' => route('menus.update', $menu),
            'method' => 'PUT',
            'isEdit' => true,
            'submitLabel' => 'Perbarui',
        ])
    </x-card>
</x-layouts.app>
