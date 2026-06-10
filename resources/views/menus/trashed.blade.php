<x-layouts.app title="Menu Terhapus">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('menus.index') }}">Menu</a></li>
        <li class="breadcrumb-item active" aria-current="page">Terhapus</li>
    </x-slot:breadcrumb>

    <x-card title="Menu yang dihapus">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Route</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menus as $menu)
                        <tr>
                            <td>
                                @if ($menu->icon)<i class="bi {{ $menu->icon }} me-1"></i>@endif
                                {{ $menu->name }}
                            </td>
                            <td><code>{{ $menu->type }}</code></td>
                            <td>{{ $menu->route ?? '—' }}</td>
                            <td class="text-end">
                                @can('restore menu')
                                    <form method="POST" action="{{ route('menus.restore', $menu) }}" class="d-inline"
                                          onsubmit="return confirm('Pulihkan menu ini beserta turunannya yang terhapus?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Pulihkan
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Tidak ada menu terhapus.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
