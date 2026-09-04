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
        'user_updated' => ':actor memperbarui pengguna :name (:email).',
        'user_deleted' => ':actor menghapus pengguna :name (:email).',
        'account_updated' => 'Akun Anda diperbarui oleh :actor.',
        'service_subscription_grace' => 'Langganan :service Anda berakhir. Perpanjang dalam :grace_days hari (hingga :grace_ends_at) atau file berlebih akan dihapus ke batas free tier.',
        'service_subscription_expired' => 'Masa grace langganan :service berakhir. Kuota free tier diberlakukan (:deleted file dihapus).',
        'inference_unregistered_model' => 'Model tidak terdaftar :resolved_model di :provider_slug ditagih dengan tarif sementara ($:input_per_million_usd / $:output_per_million_usd per 1M). Klasifikasikan di katalog model untuk menyelesaikan penagihan.',
        'vtual_management_invite_received' => ':inviter mengundang Anda untuk bergabung ke :management sebagai :role.',
    ],
    'audiences' => [
        'list_success' => 'Audiens notifikasi dimuat.',
        'show_success' => 'Audiens notifikasi dimuat.',
        'updated' => 'Audiens notifikasi diperbarui.',
        'unknown_type' => 'Tipe notifikasi tidak dikenal.',
    ],
];
