<?php

return [
    'tree_success' => 'Pohon navigasi berhasil diambil.',
    'layouts_success' => 'Tata letak navigasi berhasil diambil.',
    'list_success' => 'Daftar menu berhasil diambil.',
    'show_success' => 'Detail menu berhasil diambil.',
    'trashed_success' => 'Menu terhapus berhasil diambil.',
    'created' => 'Menu berhasil dibuat.',
    'updated' => 'Menu berhasil diperbarui.',
    'deleted' => 'Menu berhasil dihapus.',
    'restored' => 'Menu berhasil dipulihkan.',
    'not_found' => 'Menu tidak ditemukan.',
    'has_children' => 'Menu ini masih memiliki anak. Gunakan cascade=true untuk menghapus beserta turunannya.',
    'invalid_route' => 'Route harus berupa nama route yang terdaftar.',
    'position' => [
        'reference_required' => 'reference_id wajib diisi untuk penempatan before/after.',
        'reference_not_in_group' => 'reference_id tidak berada dalam grup saudara yang sama.',
        'invalid_position' => 'position harus before atau after.',
        'reference_same_menu' => 'reference_id tidak boleh sama dengan menu yang dipindahkan.',
        'reference_wrong_group' => 'menu referensi harus memiliki parent_id dan type yang sama dengan grup target.',
    ],
    'parent_restore_failed' => 'Tidak dapat memulihkan: menu induk hilang atau masih terhapus. Pulihkan induk terlebih dahulu.',
];
