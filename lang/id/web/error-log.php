<?php

return [
    'title' => 'Error log',
    'breadcrumb' => 'Error log',
    'list_title' => 'Error aplikasi',
    'show_title' => 'Detail error',
    'show_breadcrumb' => 'Detail',
    'show_card_title' => 'Grup error',
    'empty' => 'Belum ada error tercatat.',
    'view_detail' => 'Lihat',
    'back_to_list' => 'Kembali ke daftar',
    'status_updated' => 'Status diperbarui.',
    'fields' => [
        'status' => 'Status',
        'level' => 'Level',
        'message' => 'Pesan',
        'exception_class' => 'Exception',
        'location' => 'Lokasi',
        'occurrence_count' => 'Kemunculan',
        'first_seen_at' => 'Pertama terlihat',
        'last_seen_at' => 'Terakhir terlihat',
        'fingerprint' => 'Fingerprint',
        'resolved_by' => 'Diselesaikan oleh',
    ],
    'sections' => [
        'context' => 'Konteks',
        'trace' => 'Stack trace',
    ],
    'columns' => [
        'level' => 'Level',
        'message' => 'Pesan',
        'count' => 'Jumlah',
        'status' => 'Status',
        'last_seen' => 'Terakhir',
        'actions' => 'Aksi',
    ],
    'filters' => [
        'all_statuses' => 'Semua status',
        'all_levels' => 'Semua level',
        'all_types' => 'Semua tipe',
        'search' => 'Cari pesan atau class…',
        'apply' => 'Filter',
    ],
    'statuses' => [
        'open' => 'Terbuka',
        'resolved' => 'Selesai',
        'ignored' => 'Diabaikan',
    ],
    'types' => [
        'exception' => 'Exception',
        'log' => 'Log',
    ],
    'actions' => [
        'resolve' => 'Tandai selesai',
        'ignore' => 'Abaikan',
        'reopen' => 'Buka lagi',
    ],
];
