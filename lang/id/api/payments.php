<?php

return [
    'success' => 'Pembayaran berhasil diselesaikan.',
    'failed' => 'Pembayaran gagal. Silakan coba lagi.',
    'pending' => 'Pembayaran sedang diproses.',

    'gateways' => [
        'list' => 'Payment gateway berhasil diambil.',
        'show' => 'Payment gateway berhasil diambil.',
        'created' => 'Payment gateway berhasil dibuat.',
        'updated' => 'Payment gateway berhasil diperbarui.',
        'deleted' => 'Payment gateway berhasil dihapus.',
        'inactive' => 'Payment gateway yang dipilih tidak aktif.',
        'none_active' => 'Tidak ada payment gateway aktif yang dikonfigurasi.',
    ],

    'invoices' => [
        'list' => 'Invoice berhasil diambil.',
        'show' => 'Invoice berhasil diambil.',
    ],

    'subscriptions' => [
        'list' => 'Langganan berhasil diambil.',
        'show' => 'Langganan berhasil diambil.',
        'created' => 'Langganan berhasil dibuat.',
        'updated' => 'Langganan berhasil diperbarui.',
        'deleted' => 'Langganan berhasil dihapus.',
    ],

    'checkout' => [
        'active_session_pending' => 'Sesi pembayaran sebelumnya masih tertunda di penyedia. Tunggu hingga kedaluwarsa atau selesai sebelum mengganti channel.',
        'gateway_provider_mismatch' => 'Payment gateway invoice tidak cocok dengan penyedia checkout yang diminta.',
    ],

    'channels' => [
        'list' => 'Channel pembayaran berhasil diambil.',
        'show' => 'Channel pembayaran berhasil diambil.',
        'updated' => 'Channel pembayaran berhasil diperbarui.',
        'checkout_created' => 'Instruksi pembayaran dibuat.',
        'checkout_failed' => 'Semua rute pembayaran untuk channel ini gagal.',
        'route_updated' => 'Rute channel pembayaran diperbarui.',
        'not_found' => 'Channel pembayaran tidak ditemukan.',
        'not_available' => 'Channel pembayaran yang dipilih tidak tersedia.',
        'unsupported_gateway' => 'Tidak ada payment gateway yang dikonfigurasi untuk channel ini.',
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
        'invoice_not_found' => 'Tidak ada invoice iPaymu untuk order id ini.',
        'missing_transaction_id' => 'Invoice belum memiliki transaction id iPaymu.',
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
