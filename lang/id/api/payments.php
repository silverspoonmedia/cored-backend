<?php

return [
    'success' => 'Pembayaran berhasil diselesaikan.',
    'failed' => 'Pembayaran gagal. Silakan coba lagi.',
    'pending' => 'Pembayaran sedang diproses.',

    'gateways' => [
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'list' => 'Daftar gateway pembayaran berhasil dimuat.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'show' => 'Detail gateway pembayaran berhasil dimuat.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'created' => 'Gateway pembayaran berhasil dibuat.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'updated' => 'Gateway pembayaran berhasil diperbarui.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'deleted' => 'Gateway pembayaran berhasil dihapus.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'inactive' => 'Gateway pembayaran yang dipilih sedang tidak aktif.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'none_active' => 'Belum ada gateway pembayaran aktif yang dikonfigurasi.',
    ],

    'invoices' => [
        'list' => 'Daftar invoice berhasil dimuat.',
        'show' => 'Detail invoice berhasil dimuat.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'checkout_channels' => 'Kanal pembayaran untuk checkout berhasil dimuat.',
        'checkout_channels_not_payable' => 'Invoice ini belum dapat dibayar.',
    ],

    'income' => [
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'overview' => 'Ringkasan pendapatan pembayaran berhasil dimuat.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'invoices' => 'Daftar invoice pendapatan berhasil dimuat.',
    ],

    'subscriptions' => [
        'list' => 'Daftar langganan berhasil dimuat.',
        'show' => 'Detail langganan berhasil dimuat.',
        'created' => 'Langganan berhasil dibuat.',
        'updated' => 'Langganan berhasil diperbarui.',
        'deleted' => 'Langganan berhasil dihapus.',
    ],

    'checkout' => [
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'active_session_pending' => 'Sesi pembayaran sebelumnya masih tertunda di penyedia. Tunggu hingga kedaluwarsa atau selesai sebelum mengganti kanal.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'gateway_provider_mismatch' => 'Gateway pembayaran invoice tidak cocok dengan penyedia checkout yang diminta.',
    ],

    'channels' => [
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'list' => 'Kanal pembayaran berhasil dimuat.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'show' => 'Kanal pembayaran berhasil dimuat.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'updated' => 'Kanal pembayaran berhasil diperbarui.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'checkout_created' => 'Instruksi pembayaran berhasil dibuat.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'checkout_failed' => 'Semua rute pembayaran untuk kanal ini gagal.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'route_updated' => 'Rute kanal pembayaran berhasil diperbarui.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'not_found' => 'Kanal pembayaran tidak ditemukan.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'not_available' => 'Kanal pembayaran yang dipilih tidak tersedia.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'unsupported_gateway' => 'Belum ada gateway pembayaran yang mendukung kanal ini.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'policy_managed_route' => 'Rute ini dikelola oleh kebijakan perutean. Hanya status aktif yang dapat diubah.',
    ],

    'midtrans' => [
        'snap_created' => 'Transaksi Snap dibuat.',
        'charge_created' => 'Charge Core API dibuat.',
        'status_retrieved' => 'Status transaksi diambil.',
        'cancel_ok' => 'Transaksi dibatalkan.',
        'expire_ok' => 'Transaksi kedaluwarsa.',
        'refund_ok' => 'Refund diproses.',
        'approve_ok' => 'Transaksi disetujui.',
        'deny_ok' => 'Transaksi ditolak.',
        'sync_ok' => 'Status invoice disinkronkan dengan Midtrans.',
        'invoice_not_midtrans' => 'Invoice tidak terhubung ke gateway Midtrans.',
        'invoice_not_payable' => 'Invoice harus pending atau challenge untuk memulai pembayaran.',
        'external_sync_failed' => 'Tidak dapat memverifikasi status pembayaran dengan Midtrans sebelum memulai checkout.',
        'webhook_invalid_json' => 'Body JSON tidak valid.',
        'webhook_invalid_signature' => 'Signature notifikasi tidak valid.',
        'webhook_missing_order' => 'order_id tidak ada di notifikasi.',
        'webhook_ack_no_status' => 'Notifikasi diterima (status tidak dapat diselesaikan).',
        'webhook_orphan' => 'Notifikasi diterima (tidak ada invoice yang cocok).',
        'webhook_ok' => 'Notifikasi diproses.',
        'webhook_ping' => 'URL webhook Midtrans dapat dijangkau (gunakan POST untuk notifikasi).',
        'webhook_subscription_ack' => 'Notifikasi langganan diterima.',
        'open_api_public_key_missing' => 'Kunci publik Open API Midtrans belum dikonfigurasi.',
        'open_api_signature_invalid' => 'X-SIGNATURE Open API tidak valid atau tidak dapat diverifikasi.',
    ],

    'ipaymu' => [
        'snap_created' => 'Sesi checkout iPaymu dibuat.',
        'charge_created' => 'Pembayaran langsung iPaymu dibuat.',
        'status_retrieved' => 'Status transaksi diambil dari iPaymu.',
        'sync_ok' => 'Status invoice disinkronkan dengan iPaymu.',
        'invoice_not_ipaymu' => 'Invoice tidak terhubung ke gateway iPaymu.',
        'invoice_not_payable' => 'Invoice harus pending atau challenge untuk memulai pembayaran.',
        'external_sync_failed' => 'Tidak dapat memverifikasi status pembayaran dengan iPaymu sebelum memulai checkout.',
        'active_payment_exists' => 'Sesi pembayaran untuk invoice ini masih berjalan. Selesaikan atau batalkan sebelum memulai checkout baru.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'invoice_not_found' => 'Tidak ada invoice iPaymu untuk ID pesanan ini.',
        // curated at 2026-09-21T17:08:42+07:00 — id curation wave
        'missing_transaction_id' => 'Invoice belum memiliki ID transaksi iPaymu; selesaikan sesi pembayaran terlebih dahulu.',
        'buyer_required' => 'Tidak dapat menentukan detail pembeli dari request, invoiceable, atau profil pengguna yang login.',
        'webhook_invalid_json' => 'Body JSON tidak valid.',
        'webhook_invalid_signature' => 'Signature notifikasi tidak valid.',
        'webhook_invalid_va' => 'Header VA tidak cocok dengan VA iPaymu yang dikonfigurasi.',
        'webhook_ack_no_status' => 'Notifikasi diterima (status tidak dapat diselesaikan).',
        'webhook_orphan' => 'Notifikasi diterima (tidak ada invoice yang cocok).',
        'webhook_ok' => 'Notifikasi diproses.',
        'webhook_ping' => 'URL webhook iPaymu dapat dijangkau (gunakan POST untuk notifikasi).',
    ],
];
