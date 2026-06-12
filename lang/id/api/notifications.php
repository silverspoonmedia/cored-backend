<?php

return [
    'list_success' => 'Daftar notifikasi berhasil diambil.',
    'unread_count_success' => 'Jumlah notifikasi belum dibaca berhasil diambil.',
    'marked_read' => 'Notifikasi ditandai telah dibaca.',
    'marked_all_read' => 'Semua notifikasi ditandai telah dibaca.',
    'not_visible' => 'Anda tidak berwenang mengakses notifikasi ini.',
    // Template pesan per tipe notifikasi. Nilai pengganti berasal dari payload
    // `data` notifikasi (:name, :email, :actor, :role, …).
    'types' => [
        'user_created' => 'Pengguna baru :name (:email) ditambahkan oleh :actor.',
        'account_updated' => 'Akun Anda diperbarui oleh :actor.',
    ],
];
