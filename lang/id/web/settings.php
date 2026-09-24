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
        'archivd' => 'Archivd',
        'microstock' => 'Microstock',
        'vtual' => 'vTual',
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
        // Archivd
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'archivd.max_file_size_bytes' => 'Ukuran berkas maksimum (byte)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'archivd.max_files_per_batch' => 'Berkas maksimum per batch',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'archivd.max_concurrent_uploads' => 'Unggahan bersamaan maksimum',
        'archivd.max_concurrent_parts' => 'Bagian multipart bersamaan maksimum',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'archivd.chunk_size_bytes' => 'Ukuran potongan unggahan (byte)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'archivd.multipart_threshold_bytes' => 'Ambang unggahan multipart (byte)',
        'archivd.max_presigned_parts_per_response' => 'Bagian presigned maksimum per respons',
        'archivd.presign_ttl_minutes' => 'TTL URL presigned (menit)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'archivd.session_ttl_hours' => 'TTL sesi unggahan (jam)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'archivd.stale_upload_grace_hours' => 'Masa tenggang unggahan usang (jam)',
        'archivd.orphan_inactivity_minutes' => 'Timeout inaktivitas bagian yatim (menit)',
        'archivd.default_quota_bytes' => 'Kuota penyimpanan default (byte)',
        'archivd.base_owned_workspace_slots' => 'Slot workspace milik dasar',
        'archivd.disabled_regions' => 'Region penyimpanan dinonaktifkan',
        'archivd.signed_download_ttl_minutes' => 'TTL URL unduhan bertanda tangan (menit)',
        'archivd.signed_download_cache_margin_seconds' => 'Margin cache unduhan bertanda tangan (detik)',
        'archivd.public_download_signature_ttl_minutes' => 'TTL tanda tangan unduhan publik (menit)',
        'archivd.allowed_mime_prefixes' => 'Awalan tipe MIME yang diizinkan',
        'archivd.download_vault.enabled' => 'Proxy unduhan vault aktif',
        // Microstock
        'microstock.daily_free.enabled' => 'Unduhan gratis harian aktif',
        'microstock.daily_free.limit_per_user' => 'Batas unduhan gratis harian per pengguna',
        'microstock.daily_free.timezone' => 'Zona waktu reset gratis harian',
        'microstock.ai_billing.markup_multiplier' => 'Pengali markup penagihan AI',
        'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => 'Estimasi micro-USD yang ditagih per aset',
        'microstock.ai_billing.estimate_thinking_attempt_factor' => 'Faktor estimasi percobaan thinking',
        'microstock.ai.image_max_px' => 'Dimensi gambar AI maksimum (px)',
        'microstock.ai.image_jpeg_quality' => 'Kualitas JPEG gambar AI',
        'microstock.ai.max_request_image_bytes' => 'Ukuran gambar permintaan maksimum (byte)',
        'microstock.ai.http_retries' => 'Percobaan ulang HTTP AI',
        'microstock.ai.thinking_visual_attempts' => 'Percobaan visual thinking',
        'microstock.ai.batch_chunk' => 'Ukuran chunk batch AI',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.batch_min_files' => 'Berkas minimum per batch AI',
        'microstock.ai.phase.normal_after' => 'Fase AI: normal setelah (detik)',
        'microstock.ai.phase.grace_after' => 'Fase AI: grace setelah (detik)',
        'microstock.ai.stage.vision_grace_after' => 'Tahap AI: grace vision setelah (detik)',
        'microstock.ai.stage.thinking_grace_after' => 'Tahap AI: grace thinking setelah (detik)',
        'microstock.ai.stage.thinking_backlog_after' => 'Tahap AI: backlog thinking setelah (detik)',
        'microstock.ai.vision_image.delivery' => 'Mode pengiriman gambar vision',
        'microstock.ai.vision_image.variant_kind' => 'Jenis varian gambar vision',
        'microstock.ai.vision_image.signed_url_ttl_minutes' => 'TTL URL bertanda tangan vision (menit)',
        'microstock.ai.inference_provider_slug' => 'Slug provider inferensi Microstock',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.vision.connection' => 'Koneksi AI vision (kosong = default)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.thinking.connection' => 'Koneksi AI thinking (kosong = default)',
        'microstock.ai.vision.model' => 'Model vision',
        'microstock.ai.vision.free_model' => 'Model vision tier gratis (kosong = model berbayar)',
        'microstock.ai.vision.timeout' => 'Timeout HTTP vision (detik)',
        'microstock.ai.thinking.model' => 'Model thinking',
        'microstock.ai.thinking.free_model' => 'Model thinking tier gratis (kosong = model berbayar)',
        'microstock.ai.thinking.timeout' => 'Timeout HTTP thinking (detik)',
        'microstock.ai.upstream.window_seconds' => 'Jendela anggaran token upstream (detik)',
        'microstock.ai.upstream.token_budget' => 'Anggaran token upstream per jendela',
        'microstock.ai.upstream.max_concurrent_vision' => 'Permintaan vision bersamaan maksimum',
        'microstock.ai.upstream.max_concurrent_thinking' => 'Permintaan thinking bersamaan maksimum',
        'microstock.ai.upstream.collection_vision_concurrency' => 'Konkurensi vision koleksi',
        'microstock.ai.upstream.collection_thinking_concurrency' => 'Konkurensi thinking koleksi',
        'microstock.ai.upstream.solo_vision_concurrency' => 'Konkurensi vision solo',
        'microstock.ai.upstream.solo_thinking_concurrency' => 'Konkurensi thinking solo',
        'microstock.ai.upstream.release_after_seconds' => 'Lepas slot upstream setelah (detik)',
        'microstock.csv_disk' => 'Disk ekspor CSV',
        // vTual
        'vtual.credential_test.http_timeout' => 'Timeout HTTP uji kredensial (detik)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.credential_test.twitch_token_skew_seconds' => 'Jeda token Twitch (detik)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.credential_test.youtube_probe_channel_id' => 'ID kanal probe YouTube',
        'vtual.credential.failover.enabled' => 'Failover kredensial aktif',
        'vtual.credential.failover.auth_fail_threshold' => 'Ambang kegagalan auth failover',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.credential.failover.skip_minutes' => 'Durasi lewati failover (menit)',
        'vtual.credential.failover.max_retries_per_call' => 'Percobaan ulang failover maksimum per panggilan',
        'vtual.http.timeout' => 'Timeout HTTP (detik)',
        'vtual.claim.ttl_hours' => 'TTL klaim (jam)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.claim.verify_cooldown_seconds' => 'Jeda verifikasi klaim (detik)',
        'vtual.claim.max_attempts' => 'Percobaan verifikasi klaim maksimum',
        'vtual.claim.block_hours_after_max_attempts' => 'Durasi blokir klaim setelah percobaan maksimum (jam)',
        'vtual.claim.max_pending_per_user' => 'Klaim tertunda maksimum per pengguna',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.claim.accept_anyway_enabled' => 'Izinkan terima klaim tanpa verifikasi',
        'vtual.claim.discord_verify_enabled' => 'Verifikasi klaim Discord aktif',
        'vtual.claim.token_prefix' => 'Prefiks token klaim',
        'vtual.claim.token_bytes' => 'Byte acak token klaim',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_claim.ttl_hours' => 'TTL klaim kanal (jam)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_claim.verify_cooldown_seconds' => 'Jeda verifikasi klaim kanal (detik)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_claim.max_attempts' => 'Percobaan klaim kanal maksimum',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_claim.accept_anyway_enabled' => 'Izinkan terima klaim kanal tanpa verifikasi',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_claim.discord_verify_enabled' => 'Verifikasi Discord klaim kanal aktif',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_claim.token_prefix' => 'Prefiks token klaim kanal',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_claim.token_bytes' => 'Byte acak token klaim kanal',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_pool.threshold' => 'Ambang rendah pool kanal',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_pool.max_wait_minutes' => 'Tunggu maksimum pool kanal (menit)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_pool.schedule_every_minutes' => 'Interval jadwal pool kanal (menit)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_pool.resync_stale_hours' => 'Resync pool kanal setelah usang (jam)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_pool.resync_enqueue_limit' => 'Batas antrean resync pool kanal',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_pool.flush_lock_seconds' => 'Kunci flush pool kanal (detik)',
        'vtual.claim_verify_pool.threshold' => 'Ambang rendah pool verifikasi klaim',
        'vtual.claim_verify_pool.max_wait_minutes' => 'Tunggu maksimum pool verifikasi klaim (menit)',
        'vtual.claim_verify_pool.flush_lock_seconds' => 'Kunci flush pool verifikasi klaim (detik)',
        'vtual.content_pool.threshold' => 'Ambang rendah pool konten',
        'vtual.content_pool.max_wait_minutes' => 'Tunggu maksimum pool konten (menit)',
        'vtual.content_pool.flush_lock_seconds' => 'Kunci flush pool konten (detik)',
        'vtual.feed_poll.interval_minutes' => 'Interval poll feed (menit)',
        'vtual.live_poll.interval_minutes' => 'Interval poll live CCV (menit)',
        'vtual.websub.lease_seconds' => 'Lease WebSub (detik)',
        'vtual.websub.renew_before_hours' => 'Perpanjang WebSub sebelum kedaluwarsa (jam)',
        'vtual.websub.max_body_bytes' => 'Ukuran maks body notifikasi WebSub (byte)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.landing.event.title' => 'Judul acara landing',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.landing.event.starts_at' => 'Waktu mulai acara landing',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.landing.event.body' => 'Isi acara landing',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.content_classification.ai.enabled' => 'Audit AI aktif (klasifikasi konten)',
        'vtual.content_classification.ai.model' => 'Model audit AI',
        'vtual.content_classification.ai.max_tokens' => 'Token respons maksimum audit AI',
        'vtual.content_classification.ai.timeout' => 'Timeout audit AI (detik)',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.content_classification.ai.min_confidence' => 'Keyakinan minimum audit AI',
        'vtual.content_classification.ai.cache_ttl_hours' => 'Cache hasil audit AI (jam)',
        'vtual.content_classification.ai.connection' => 'Koneksi audit AI (kosong = default)',
    ],
    'hints' => [
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.mail.from_address' => 'Alamat pengirim global untuk email keluar (kredensial transport tetap di .env).',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.payment.default_buyer_phone' => 'Telepon cadangan saat profil pembeli belum memiliki nomor (syarat iPaymu).',
        'platform.frankfurter.timeout' => 'Timeout HTTP untuk lookup FX Frankfurter.',
        'microstock.ai.vision.free_model' => 'Override model tier gratis opsional; kosong mewarisi model vision berbayar.',
        'microstock.ai.thinking.free_model' => 'Override model tier gratis opsional; kosong mewarisi model thinking berbayar.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.vision.connection' => 'Slug koneksi AI (Pengaturan → Koneksi AI) untuk tahap vision; kosong memakai koneksi default.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.thinking.connection' => 'Slug koneksi AI (Pengaturan → Koneksi AI) untuk tahap thinking; kosong memakai koneksi default.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.upstream.window_seconds' => 'Jendela referensi anggaran token upstream (penegakan melalui batas konkurensi).',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.credential_test.youtube_probe_channel_id' => 'ID kanal publik untuk uji cepat YouTube Data API.',
        'platform.jwt.refresh_skew' => 'Refresh token akses beberapa menit sebelum kedaluwarsa.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.notifications.delivery' => 'Realtime memakai WebSocket dan membutuhkan kredensial PUSHER_* / broadcast yang valid; jika tidak, lonceng kembali ke polling HTTP.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'platform.payment.channel_fee_bearer' => 'Siapa yang menanggung biaya kanal pembayaran: merchant atau pelanggan.',
        'platform.inference.default_provider_slug' => 'Provider cadangan saat tidak ada yang ditentukan pada permintaan.',
        'platform.inference.default_input_per_million_usd' => 'Tarif penagihan sementara untuk model terdaftar tanpa harga katalog.',
        'platform.inference.unknown_input_per_million_usd' => 'Tarif penagihan sementara untuk model yang belum terdaftar.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.phase.normal_after' => 'Detik sebelum aset beralih dari jalur cepat ke pemrosesan normal.',
        'microstock.ai.phase.grace_after' => 'Detik sebelum penanganan masa tenggang dimulai.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.stage.vision_grace_after' => 'Detik sebelum tahap vision masuk mode tenggang.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.stage.thinking_grace_after' => 'Detik sebelum tahap thinking masuk mode tenggang.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'microstock.ai.stage.thinking_backlog_after' => 'Detik sebelum job thinking berpindah ke antrean backlog.',
        'vtual.credential.failover.enabled' => 'Rotasi ke kredensial berikutnya setelah kegagalan auth berulang.',
        'vtual.claim.accept_anyway_enabled' => 'Izinkan pengguna menerima klaim meski verifikasi tidak meyakinkan.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.claim.discord_verify_enabled' => 'Izinkan pengguna memverifikasi klaim yatim dengan mencocokkan akun YouTube/Twitch yang tertaut di Discord.',
        // curated at 2026-09-21T17:02:15+07:00 — id curation wave
        'vtual.channel_claim.discord_verify_enabled' => 'Izinkan pemilik profil memverifikasi klaim kanal melalui akun YouTube/Twitch yang tertaut di Discord.',
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
