<x-layouts.app title="Dashboard">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
    </x-slot:breadcrumb>

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Selamat datang, {{ $profile['name'] }}">
                <p class="text-muted mb-3">{{ $profile['email'] }}</p>

                <div class="mb-3">
                    <strong>Role:</strong>
                    @forelse ($profile['roles'] as $role)
                        <span class="badge text-bg-primary">{{ $role }}</span>
                    @empty
                        <span class="text-muted">Tidak ada role.</span>
                    @endforelse
                </div>

                <div class="mb-3">
                    <strong>Status email:</strong>
                    @if ($profile['email_verified_at'])
                        <span class="badge text-bg-success">Terverifikasi</span>
                    @else
                        <span class="badge text-bg-warning">Belum terverifikasi</span>
                        <form method="POST" action="{{ route('verification.send') }}" class="d-inline ms-2">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Kirim ulang verifikasi</button>
                        </form>
                    @endif
                </div>

                <div>
                    <strong>Permission:</strong>
                    @forelse ($profile['permissions'] as $permission)
                        <span class="badge text-bg-secondary">{{ $permission }}</span>
                    @empty
                        <span class="text-muted">Tidak ada permission khusus.</span>
                    @endforelse
                </div>
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="Akses cepat">
                <div class="d-grid gap-2">
                    @can('view user')
                        <a href="{{ route('users.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-people me-1"></i> Manajemen Pengguna
                        </a>
                    @endcan
                    @can('view menu')
                        <a href="{{ route('menus.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-list-nested me-1"></i> Navigasi Menu
                        </a>
                    @endcan
                    <a href="{{ route('profile.password.edit') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-key me-1"></i> Ubah Kata Sandi
                    </a>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>
