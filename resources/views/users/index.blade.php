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
            <table id="users-table" class="table table-hover align-middle mb-0 w-100"></table>
        </div>
    </x-card>

    <x-slot:scripts>
        <x-partials.datatable-assets />
        <x-partials.datatable-row-actions />

        <script>
            $(function () {
                $('#users-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: '{{ route('users.datatable') }}',
                    order: [[0, 'asc']],
                    language: {
                        emptyTable: 'Tidak ada pengguna yang dapat Anda lihat.',
                        zeroRecords: 'Tidak ada pengguna yang cocok dengan pencarian.',
                    },
                    columns: [
                        { data: 'name', title: 'Nama' },
                        { data: 'email', title: 'Email' },
                        {
                            data: 'roles',
                            title: 'Role',
                            orderable: false,
                            render: function (roles) {
                                if (!Array.isArray(roles) || roles.length === 0) {
                                    return '<span class="text-muted">&mdash;</span>';
                                }

                                return roles.map(function (role) {
                                    return '<span class="badge text-bg-light border me-1">' + role.name + '</span>';
                                }).join('');
                            },
                        },
                        {
                            data: 'email_verified_at',
                            title: 'Status',
                            render: function (verifiedAt) {
                                if (verifiedAt) {
                                    return '<span class="badge text-bg-success">Terverifikasi</span>';
                                }

                                return '<span class="badge text-bg-warning">Belum</span>';
                            },
                        },
                        {
                            data: 'actions',
                            title: 'Aksi',
                            orderable: false,
                            searchable: false,
                            className: 'text-end',
                            render: function (actions) {
                                return renderDatatableActions(actions);
                            },
                        },
                    ],
                });
            });
        </script>
    </x-slot:scripts>
</x-layouts.app>
