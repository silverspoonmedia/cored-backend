<?php

return [
    'connection' => [
        'list_success' => 'Koneksi AI berhasil diambil.',
        'show_success' => 'Koneksi AI berhasil diambil.',
        'created' => 'Koneksi AI dibuat. Jalankan Uji untuk mengaktifkannya.',
        'updated' => 'Koneksi AI diperbarui. Jalankan Uji untuk mengaktifkannya kembali.',
        'deleted' => 'Koneksi AI berhasil dihapus.',
        'default_updated' => 'Koneksi AI default diperbarui.',
        'slug_required' => 'Slug koneksi wajib diisi.',
        'slug_taken' => 'Slug koneksi itu sudah dipakai.',
        'label_required' => 'Label koneksi wajib diisi.',
        'endpoint_invalid' => 'Endpoint harus berupa URL http(s) yang valid.',
        'test_model_required' => 'Model uji wajib diisi untuk memeriksa koneksi ini.',
        'payload_invalid' => 'Payload rahasia yang tersimpan tidak valid. Masukkan ulang API key.',
        'payload_missing' => 'Payload rahasia kehilangan field wajib: :field.',
        'payload_unknown_key' => 'Field rahasia tidak dikenal: :field.',
        'default_requires_active' => 'Hanya koneksi aktif (sudah diuji) yang bisa menjadi default.',
        'delete_requires_default_handover' => 'Jadikan koneksi lain sebagai default sebelum menghapus yang ini.',
        'test_not_configured' => 'Koneksi belum punya endpoint, API key, atau model uji.',
        'test_unreachable' => 'Endpoint tidak dapat dijangkau: :error',
        'test_unauthorized' => 'Endpoint menolak API key (401/403).',
        'test_http_failed' => 'Endpoint menjawab dengan HTTP :status.',
        'test_http_failed_with_body' => 'Endpoint menjawab dengan HTTP :status: :detail',
        'test_passed' => 'Uji koneksi berhasil.',
    ],
];
