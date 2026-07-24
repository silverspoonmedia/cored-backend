<?php

declare(strict_types=1);

return [
    // Gate / middleware
    'auth_required' => 'Autentikasi diperlukan.',
    'unknown_product' => 'Domain produk tidak dikenal: :product.',
    'forbidden' => 'Domain produk ini bersifat privat. Akses hanya untuk Root, Admin, dan beta tester-nya.',

    // Admin API
    'list_success' => 'Pengaturan akses domain produk berhasil diambil.',
    'updated' => 'Visibilitas domain produk diperbarui.',
    'invalid_visibility' => 'Visibilitas harus public atau private.',

    // UI
    'title' => 'Akses Domain Produk',
    'subtitle' => 'Atur domain produk mana yang publik atau dibatasi untuk beta tester.',
    'empty' => 'Belum ada domain produk terdaftar.',
    'col_domain' => 'Domain Produk',
    'col_visibility' => 'Visibilitas',
    'visibility_public' => 'Publik',
    'visibility_private' => 'Privat',
    'public_hint' => 'Siapa pun dapat mengakses domain produk ini.',
    'private_hint' => 'Hanya Root, Admin, dan beta tester yang dapat mengakses.',
    'beta_role' => 'Peran beta: :role',
    'toggle_updated' => 'Visibilitas diperbarui.',
    'toggle_failed' => 'Gagal memperbarui visibilitas.',
];
