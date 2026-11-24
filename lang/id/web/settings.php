<?php

return [
    'title' => 'Pengaturan',
    // curated at 2026-09-21T17:02:15+07:00 — id curation wave
    'subtitle' => 'Konfigurasi operasional yang dikelola melalui antarmuka admin.',
    // curated at 2026-09-21T17:02:15+07:00 — id curation wave
    'read_only' => 'Anda hanya memiliki akses baca untuk pengaturan ini.',
    'source' => 'Sumber saat ini: :source',
    'groups' => [
        'platform' => 'Platform',
        'notification_audiences' => 'Audiens notifikasi',
        'currencies' => 'Mata uang',
    ],
    'fields' => [
        // Platform — JWT
        'platform.jwt.ttl' => 'TTL akses JWT (menit)',
        'platform.jwt.refresh_ttl' => 'Jendela refresh JWT (menit)',
        'platform.jwt.remember_ttl' => 'TTL remember-me JWT (menit)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.jwt.refresh_skew' => 'Jeda refresh senyap JWT (menit)',
        'platform.jwt.blacklist_grace_period' => 'Masa tenggang blacklist JWT (detik)',
        // Platform — auth / 2FA
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.auth.default_role' => 'Peran default untuk pengguna baru',
        'platform.auth.verification_expire' => 'Kedaluwarsa tautan verifikasi email (menit)',
        'platform.two_factor.window' => 'Jendela validasi TOTP 2FA',
        'platform.two_factor.issuer' => 'Nama penerbit 2FA (aplikasi autentikator)',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => 'hCaptcha aktif',
        // Platform — notifications
        'platform.notifications.delivery' => 'Mode pengiriman notifikasi',
        'platform.notifications.polling_interval' => 'Interval polling notifikasi (detik)',
        // Platform — error log
        'platform.errorlog.enabled' => 'Log error persisten aktif',
        'platform.errorlog.clean_after_days' => 'Retensi log error (hari)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.errorlog.max_message_length' => 'Panjang maksimum pesan log error',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.errorlog.max_trace_length' => 'Panjang maksimum trace log error',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.errorlog.max_context_json_length' => 'Panjang maksimum JSON konteks log error',
        // Platform — activity log
        'platform.activitylog.enabled' => 'Log aktivitas aktif',
        'platform.activitylog.buffer_enabled' => 'Buffer log aktivitas aktif',
        // Platform — localization
        'platform.localization.locale_cookie' => 'Nama cookie locale',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => 'Umur maksimum snapshot koneksi Discord (menit)',
        'platform.localization.locale_cookie_minutes' => 'Masa hidup cookie locale (menit)',
        // Platform — payment
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.payment.channel_fee_bearer' => 'Penanggung biaya kanal',
        'platform.payment.reporting_timezone' => 'Zona waktu pelaporan pembayaran',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.payment.prefer_channel_checkout' => 'Utamakan checkout kanal asli',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.payment.default_buyer_phone' => 'Telepon pembeli default (cadangan iPaymu)',
        // Platform — mail identity
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.mail.from_address' => 'Alamat pengirim email',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.mail.from_name' => 'Nama pengirim email',
        // Platform — service
        'platform.service.grace_days' => 'Hari masa tenggang layanan',
        'platform.service.settlement_currency' => 'Mata uang settlement',
        'platform.service.purchase_max_quantity' => 'Kuantitas pembelian layanan maksimum',
        // Platform — inference fallbacks
        'platform.inference.default_provider_slug' => 'Slug provider inferensi default',
        'platform.inference.default_input_per_million_usd' => 'Tarif input default (USD per 1M token)',
        'platform.inference.default_output_per_million_usd' => 'Tarif output default (USD per 1M token)',
        'platform.inference.unknown_input_per_million_usd' => 'Tarif input model tidak dikenal (USD per 1M token)',
        'platform.inference.unknown_output_per_million_usd' => 'Tarif output model tidak dikenal (USD per 1M token)',
        // Platform — Frankfurter FX
        'platform.frankfurter.cache_ttl' => 'Jendela kesegaran FX Frankfurter (detik)',
        'platform.frankfurter.timeout' => 'Timeout HTTP Frankfurter FX (detik)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
    ],
    'hints' => [
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.mail.from_address' => 'Alamat pengirim global untuk email keluar (kredensial transport tetap di .env).',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.payment.default_buyer_phone' => 'Telepon cadangan saat profil pembeli belum memiliki nomor (syarat iPaymu).',
        'platform.frankfurter.timeout' => 'Timeout HTTP untuk lookup FX Frankfurter.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.jwt.refresh_skew' => 'Refresh token akses beberapa menit sebelum kedaluwarsa.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.notifications.delivery' => 'Realtime memakai WebSocket dan membutuhkan kredensial PUSHER_* / broadcast yang valid; jika tidak, lonceng kembali ke polling HTTP.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.payment.channel_fee_bearer' => 'Siapa yang menanggung biaya kanal pembayaran: merchant atau pelanggan.',
        'platform.inference.default_provider_slug' => 'Provider cadangan saat tidak ada yang ditentukan pada permintaan.',
        'platform.inference.default_input_per_million_usd' => 'Tarif penagihan sementara untuk model terdaftar tanpa harga katalog.',
        'platform.inference.unknown_input_per_million_usd' => 'Tarif penagihan sementara untuk model yang belum terdaftar.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => 'Berapa lama snapshot koneksi Discord tersimpan dipercaya sebelum kegagalan verifikasi memicu penyegaran langsung melalui Discord API.',
    ],
    'options' => [
        'platform' => [
            'notifications' => [
                'delivery' => [
                    // curated at 2026-09-23T12:37:01+07:00 — ko curation wave: id notification delivery options aligned with the SPA
                    'polling' => 'Polling HTTP',
                    // curated at 2026-09-23T12:37:01+07:00 — ko curation wave: id notification delivery options aligned with the SPA
                    'realtime' => 'WebSocket (real-time)',
                ],
            ],
        ],
    ],
];
