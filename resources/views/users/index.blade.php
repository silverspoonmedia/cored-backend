<x-layouts.app title="Manajemen Pengguna">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Pengguna</li>
    </x-slot:breadcrumb>

    <x-card title="Daftar pengguna">
        @can('create user')
            <x-slot:actions>
                <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Tambah pengguna
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @forelse ($user->roles as $role)
                                    <span class="badge text-bg-light border">{{ $role->name }}</span>
                                @empty
                                    <span class="text-muted">&mdash;</span>
                                @endforelse
                            </td>
                            <td>
                                @if ($user->email_verified_at)
                                    <span class="badge text-bg-success">Terverifikasi</span>
                                @else
                                    <span class="badge text-bg-warning">Belum</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('update', $user)
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-info">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @if ($user->id !== $actorId)
                                    @can('delete', $user)
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline"
                                              onsubmit="return confirm('Yakin ingin menghapus pengguna ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Tidak ada pengguna yang dapat Anda lihat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
