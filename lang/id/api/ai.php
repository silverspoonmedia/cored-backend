<?php

return [
    'connection' => [
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'list_success' => 'Koneksi AI berhasil dimuat.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'show_success' => 'Koneksi AI berhasil dimuat.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'created' => 'Koneksi AI berhasil dibuat. Jalankan uji untuk mengaktifkannya.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'updated' => 'Koneksi AI berhasil diperbarui. Jalankan uji untuk mengaktifkannya kembali.',
        'deleted' => 'Koneksi AI berhasil dihapus.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'default_updated' => 'Koneksi AI bawaan berhasil diperbarui.',
        'slug_required' => 'Slug koneksi wajib diisi.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'slug_taken' => 'Slug koneksi tersebut sudah dipakai.',
        'label_required' => 'Label koneksi wajib diisi.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'endpoint_invalid' => 'Endpoint harus berupa URL http(s) yang valid.',
        'test_model_required' => 'Model uji wajib diisi untuk memeriksa koneksi ini.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'payload_invalid' => 'Payload rahasia yang tersimpan tidak valid. Masukkan ulang kunci API.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'payload_missing' => 'Payload rahasia tidak memiliki kolom wajib: :field.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'payload_unknown_key' => 'Kolom rahasia tidak dikenal: :field.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'default_requires_active' => 'Hanya koneksi aktif (sudah diuji) yang dapat dijadikan bawaan.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'delete_requires_default_handover' => 'Jadikan koneksi lain sebagai bawaan sebelum menghapus koneksi ini.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'test_not_configured' => 'Koneksi belum memiliki endpoint, kunci API, atau model uji.',
        'test_unreachable' => 'Endpoint tidak dapat dijangkau: :error',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'test_unauthorized' => 'Endpoint menolak kunci API (401/403).',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'test_http_failed' => 'Endpoint merespons dengan HTTP :status.',
        // curated at 2026-09-21T17:08:20+07:00 — id curation wave
        'test_http_failed_with_body' => 'Endpoint merespons dengan HTTP :status: :detail',
        'test_passed' => 'Uji koneksi berhasil.',
    ],
];
