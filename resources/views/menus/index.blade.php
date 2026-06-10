<x-layouts.app title="Navigasi Menu">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Menu</li>
    </x-slot:breadcrumb>

    <x-card title="Daftar menu">
        @can('manage menu')
            <x-slot:actions>
                <a href="{{ route('menus.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Tambah menu
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Route</th>
                        <th>Urutan</th>
                        <th>Atribut</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menus as $menu)
                        <tr>
                            <td>
                                <span style="padding-left: {{ $menu['type'] === 'c' ? 32 : ($menu['type'] === 'p' ? 16 : 0) }}px;">
                                    @if ($menu['icon'])<i class="bi {{ $menu['icon'] }} me-1"></i>@endif
                                    {{ $menu['name'] }}
                                </span>
                            </td>
                            <td><code>{{ $menu['type'] }}</code></td>
                            <td>{{ $menu['route'] ?? '—' }}</td>
                            <td>{{ $menu['order'] ?? '—' }}</td>
                            <td>
                                @if ($menu['is_authenticate'])<span class="badge text-bg-info">auth</span>@endif
                                @if ($menu['is_guest_only'])<span class="badge text-bg-secondary">guest</span>@endif
                            </td>
                            <td class="text-end">
                                @can('manage menu')
                                    <a href="{{ route('menus.edit', $menu['id']) }}" class="btn btn-sm btn-info">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('menus.destroy', $menu['id']) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <div class="btn-group">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus"
                                                    onclick="return confirm('Hapus menu ini?');">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <button type="submit" name="cascade" value="1" class="btn btn-sm btn-outline-danger"
                                                    title="Hapus beserta turunannya"
                                                    onclick="return confirm('Hapus menu ini beserta SELURUH turunannya?');">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Belum ada menu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
