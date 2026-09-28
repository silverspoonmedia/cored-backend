<?php

return [
    'creator' => [
        'list_success' => 'Daftar kreator berhasil dimuat.',
        'owned_list_success' => 'Profil vTuber milik Anda berhasil dimuat.',
        'show_success' => 'Profil kreator berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'created' => 'Kreator berhasil dibuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'orphan_created' => 'Kreator tanpa pemilik berhasil dibuat (dapat diklaim).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'updated' => 'Kreator berhasil diperbarui.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'archived' => 'Kreator berhasil diarsipkan.',
        'name_required' => 'Nama tampilan wajib diisi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'slug_taken' => 'Slug kreator ini sudah digunakan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'not_found' => 'Profil kreator tidak ditemukan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'self_create_disabled' => 'Pembuatan profil langsung dinonaktifkan. Klaim profil yang sudah ada atau minta staf menyiapkannya.',
    ],
    'orphan' => [
        'list_success' => 'Daftar kreator tanpa pemilik berhasil dimuat.',
        'show_success' => 'Kreator tanpa pemilik berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'not_claimable' => 'Profil ini bukan lagi yatim (sudah diklaim atau tidak aktif).',
    ],
    'vtuber' => [
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'show_success' => 'Profil vTuber berhasil dimuat.',
        'not_found' => 'Profil vTuber tidak ditemukan atau tidak tersedia.',
    ],
    'public' => [
        'directory_success' => 'Direktori vTuber publik berhasil dimuat.',
        'show_success' => 'Profil vTuber publik berhasil dimuat.',
        'contents_success' => 'Umpan konten publik berhasil dimuat.',
        'landing_success' => 'Payload landing publik berhasil dimuat.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'management_show_success' => 'Profil manajemen publik berhasil dimuat.',
    ],
    'merge' => [
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'success' => 'Profil berhasil digabungkan. Profil sumber diarsipkan; datanya kini ada di profil tujuan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'self' => 'Profil tidak dapat digabungkan ke dirinya sendiri.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'owner_required' => 'Anda harus menjadi pemilik profil sumber dan tujuan untuk menggabungkan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'destination_archived' => 'Profil tujuan sudah diarsipkan dan tidak dapat menerima penggabungan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'source_archived' => 'Profil sumber sudah diarsipkan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'channel_clash' => 'Kedua profil memiliki channel platform yang sama (:external_id). Arsipkan atau hapus salah satu channel terlebih dahulu.',
    ],
    'claim' => [
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'claimable_success' => 'Daftar kreator yang dapat diklaim berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'list_success' => 'Klaim Anda berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'show_success' => 'Klaim berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'started' => 'Klaim dimulai. Tempel token ke bagian About/bio channel, lalu verifikasi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'verified' => 'Klaim berhasil diverifikasi. Anda kini menjadi pemiliknya.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'verify_queued' => 'Verifikasi dimasukkan ke antrean. Proses berjalan saat batch siap (maksimal 50 klaim tertunda atau setelah jendela tunggu berakhir).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'verify_already_queued' => 'Verifikasi untuk klaim ini sudah ada di antrean.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'blocked' => 'Anda belum dapat mengklaim profil ini. Coba lagi setelah masa blokir berakhir.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'cancelled' => 'Klaim dibatalkan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'accepted_anyway' => 'Klaim diterima tanpa pemeriksaan bio (staf).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'not_claimable' => 'Kreator ini tidak dapat diklaim (sudah memiliki pemilik atau tidak aktif).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'channel_mismatch' => 'Channel bukti harus milik kreator yang diklaim.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'token_not_found' => 'Token klaim tidak ditemukan di deskripsi/bio channel. Tambahkan token lalu coba lagi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'not_pending' => 'Klaim ini tidak berstatus tertunda.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'already_owned' => 'Kreator ini sudah memiliki pemilik. Klaim tidak dapat diselesaikan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'expired' => 'Klaim ini sudah kedaluwarsa. Mulai klaim baru.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'max_attempts' => 'Percobaan verifikasi untuk klaim ini terlalu banyak. Mulai klaim baru atau tunggu bantuan staf.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'cooldown' => 'Tunggu :seconds detik sebelum memverifikasi lagi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'accept_anyway_disabled' => 'Terima tanpa verifikasi dinonaktifkan di lingkungan ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'discord_verify_disabled' => 'Verifikasi klaim lewat Discord dinonaktifkan di lingkungan ini.',
        'discord_not_connected' => 'Hubungkan Discord dari Akun terhubung sebelum menggunakan verifikasi Discord.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'discord_connections_failed' => 'Akun tertaut Discord tidak dapat dimuat. Coba hubungkan ulang Discord.',
        'discord_no_match' => 'Tidak ada akun YouTube atau Twitch terverifikasi yang cocok di profil Discord Anda.',
        'discord_verify_unsupported_platform' => 'Verifikasi Discord tidak tersedia untuk platform ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'channel_missing' => 'Channel bukti klaim tidak ditemukan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'max_pending' => 'Anda sudah memiliki :max klaim tertunda. Batalkan salah satu atau selesaikan verifikasi terlebih dahulu.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'pending_success' => 'Daftar klaim tertunda berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'superseded' => 'Klaim lain untuk kreator ini sudah selesai. Klaim ini ditutup.',
    ],
    'management' => [
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'list_success' => 'Daftar manajemen berhasil dimuat.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'show_success' => 'Manajemen berhasil dimuat.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'created' => 'Manajemen berhasil dibuat.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'updated' => 'Manajemen berhasil diperbarui.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'deleted' => 'Manajemen berhasil diarsipkan.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'name_required' => 'Nama manajemen wajib diisi.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'slug_taken' => 'Slug manajemen ini sudah digunakan.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'creator_assigned' => 'Kreator berhasil ditugaskan ke manajemen.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'creator_unassigned' => 'Kreator berhasil dilepas dari manajemen.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'assignment_not_found' => 'Penugasan manajemen yang aktif tidak ditemukan.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'not_found' => 'Profil manajemen tidak ditemukan atau tidak tersedia.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'members_list_success' => 'Anggota manajemen berhasil dimuat.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'member_added' => 'Anggota berhasil ditambahkan ke manajemen.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'member_updated' => 'Peran anggota berhasil diperbarui.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'member_already_exists' => 'Pengguna ini sudah menjadi anggota manajemen.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'member_not_found' => 'Anggota manajemen tidak ditemukan.',
        'role_invalid' => 'Peran anggota atau pengguna tidak valid.',
        'cannot_edit_self' => 'Anda tidak dapat mengubah atau menghapus keanggotaan sendiri.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'last_owner' => 'Manajemen harus memiliki setidaknya satu pemilik.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'invites_list_success' => 'Undangan manajemen berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'invite_sent' => 'Undangan berhasil dikirim.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'invite_cancelled' => 'Undangan berhasil dibatalkan.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'invite_accepted' => 'Undangan berhasil diterima. Selamat bergabung di manajemen.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'invite_declined' => 'Undangan ditolak.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'invite_already_pending' => 'Sudah ada undangan tertunda untuk email ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'invite_not_actionable' => 'Undangan ini tidak dapat diubah lagi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'invite_expired' => 'Undangan ini sudah kedaluwarsa atau tidak lagi valid.',
        'invite_wrong_account' => 'Undangan ini milik akun lain.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'invite_user_not_found' => 'Tidak ada pengguna terdaftar dengan alamat email ini.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'owner_invite_not_claimable' => 'Manajemen ini sudah memiliki pemilik. Undangan pemilik hanya tersedia selama belum diklaim.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'creator_invite_mode_invalid' => 'Mode undangan kreator harus "existing" atau "new".',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'creator_invite_not_owner' => 'Akun yang diundang bukan pemilik profil vTuber yang dipilih.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'creator_already_linked' => 'Profil vTuber tersebut sudah tertaut ke manajemen ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'creator_invite_name_required' => 'Nama tampilan untuk profil vTuber baru wajib diisi.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'events_list_success' => 'Acara manajemen berhasil dimuat.',
        'event_created' => 'Acara berhasil dibuat.',
        // curated at 2026-09-24T18:10:00+07:00 — id curation wave: vTual management event edit
        'event_updated' => 'Acara berhasil diperbarui.',
        'event_cancelled' => 'Acara berhasil dibatalkan.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'event_not_found' => 'Acara tidak ditemukan untuk manajemen ini.',
        'event_not_actionable' => 'Acara ini tidak dapat diubah lagi.',
        // curated at 2026-09-27T19:30:00+07:00 — id curation wave: pemberian akses per kreator
        'grants_list_success' => 'Pemberian akses kreator dimuat.',
        'grant_created' => 'Akses kreator diberikan.',
        'grant_revoked' => 'Pemberian akses kreator dibatalkan.',
        'grant_not_found' => 'Pemberian akses kreator tidak ditemukan.',
        'grant_capability_invalid' => 'Tingkat akses untuk pemberian ini tidak valid.',
        'grant_creator_not_assigned' => 'Profil kreator harus ditautkan ke manajemen ini sebelum akses dapat diberikan.',
    ],
    'mail' => [
        'invite_subject' => 'Undangan bergabung ke :management',
        'creator_invite_subject' => 'Undangan profil vTuber dari :management',
        'creator_invite_line' => 'Anda diundang menautkan profil vTuber Anda ke :management.',
        'invite_greeting' => 'Halo!',
        'invite_line' => 'Anda diundang untuk bergabung ke :management sebagai :role.',
        'invite_action' => 'Tinjau undangan',
        'invite_expiry' => 'Undangan ini kedaluwarsa pada :date.',
    ],
    'channel' => [
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'list_success' => 'Daftar channel berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'show_success' => 'Detail channel berhasil dimuat.',
        'created' => 'Channel berhasil didaftarkan dan diverifikasi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'seeded' => 'Channel berhasil diresolusi dan didaftarkan (dibuat staf; tanpa klaim bio).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'registered_pending' => 'Channel dimasukkan ke antrean resolusi batch (tanpa panggilan API langsung).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'refreshed' => 'Profil channel berhasil disegarkan dan diverifikasi dari platform.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'refresh_queued' => 'Penyegaran channel dimasukkan ke antrean (digabung dengan permintaan tertunda lain).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'archived' => 'Channel berhasil diarsipkan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'deleted' => 'Channel tertunda berhasil dihapus.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'pending_not_archivable' => 'Channel tertunda tidak dapat diarsipkan. Hapus saja.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'not_pending_delete' => 'Hanya channel tertunda yang dapat dihapus dengan cara ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'restored' => 'Channel berhasil dipulihkan ke aktif.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'promoted' => 'Channel berhasil dipublikasikan. Pengisian profil dan konten lama dimasukkan ke antrean.',
        'not_private_discovered' => 'Hanya channel private yang ditemukan dapat dipromosikan.',
        'promote_not_discovered' => 'Hanya channel yang ditemukan via Discord dapat dipromosikan melalui aksi ini.',
        'promote_unsupported_platform' => 'Publikasi tidak didukung untuk platform ini.',
        'not_archived' => 'Channel tidak dalam status arsip.',
        'lifecycle_cooldown' => 'Tunggu :seconds detik sebelum mengarsipkan atau memulihkan channel ini lagi.',
        'lifecycle_rate_limited' => 'Terlalu banyak aksi arsip/pulihkan channel. Coba lagi nanti.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'archived_list_success' => 'Daftar channel yang diarsipkan berhasil dimuat.',
        'platform_unknown' => 'Platform tidak dikenal.',
        'platform_disabled' => 'Platform ini belum diaktifkan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'external_id_required' => 'ID channel eksternal wajib diisi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'external_id_taken' => 'Channel dengan ID platform ini sudah ada.',
        'duplicate_on_creator' => 'Channel platform ini sudah terdaftar pada profil vTuber ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'profile_required' => 'Profil channel wajib diisi (ID, handle, login, atau URL).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'profile_invalid' => 'Profil channel tidak dapat diurai. Gunakan ID platform, @handle/login, atau URL profil.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'profile_platform_mismatch' => 'URL profil ini milik platform lain. Ganti pilihan platform atau tempel profil yang sesuai.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'profile_not_found' => 'Channel tidak ditemukan untuk profil ini di platform tersebut.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'profile_unsupported_platform' => 'Resolusi profil tidak didukung untuk platform ini.',
    ],
    'channel_claim' => [
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'list_success' => 'Klaim channel berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'show_success' => 'Klaim channel berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'started' => 'Klaim channel dimulai. Tempel token ke bagian About/bio channel, lalu verifikasi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'verified' => 'Channel berhasil diverifikasi dan diikat ke profil ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'cancelled' => 'Klaim channel dibatalkan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'accepted_anyway' => 'Channel diterima tanpa pemeriksaan bio (staf).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'token_not_found' => 'Token klaim tidak ditemukan di deskripsi/bio channel. Tambahkan token lalu coba lagi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'not_pending' => 'Klaim channel ini tidak berstatus tertunda.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'expired' => 'Klaim channel ini sudah kedaluwarsa. Mulai klaim baru.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'max_attempts' => 'Percobaan verifikasi untuk klaim channel ini terlalu banyak. Mulai klaim baru atau tunggu bantuan staf.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'cooldown' => 'Tunggu :seconds detik sebelum memverifikasi lagi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'accept_anyway_disabled' => 'Terima tanpa verifikasi dinonaktifkan di lingkungan ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'pending_other' => 'Pengguna lain sudah memiliki klaim tertunda untuk channel platform ini.',
        'discord_verify_disabled' => 'Verifikasi Discord untuk klaim channel dinonaktifkan.',
        'discord_verify_unsupported_platform' => 'Verifikasi Discord tidak didukung untuk platform ini.',
        'discord_not_connected' => 'Hubungkan Discord di Profil → Akun terhubung sebelum verifikasi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'discord_connections_failed' => 'Koneksi Discord tidak dapat dimuat. Coba lagi atau gunakan verifikasi token bio.',
        'discord_no_match' => 'Tidak ada koneksi Discord terverifikasi yang cocok dengan channel platform ini.',
        'discord_verified' => 'Channel diverifikasi via Discord dan terikat ke profil ini.',
    ],
    'link' => [
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'list_success' => 'Tautan eksternal berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'created' => 'Tautan eksternal berhasil ditambahkan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'updated' => 'Tautan eksternal berhasil diperbarui.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'deleted' => 'Tautan eksternal berhasil dihapus.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'url_required' => 'URL tautan wajib diisi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'not_creator' => 'Tautan ini bukan milik profil vTuber.',
    ],
    'content' => [
        'external_id_required' => 'ID konten platform wajib diisi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'appearance_primary_via_set' => 'Channel host utama tidak dapat ditambahkan sebagai penampilan. Gunakan setPrimary.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'appearance_role_invalid' => 'Peran penampilan konten tidak valid.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'appearance_platform_mismatch' => 'Channel penampilan harus berada di platform yang sama dengan konten.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'appearance_already_primary' => 'Channel ini sudah menjadi host utama konten.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'appearance_already_attached' => 'Channel ini sudah tertaut sebagai penampilan pada konten.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'appearance_not_found' => 'Penampilan konten untuk channel ini tidak ditemukan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'cannot_detach_primary' => 'Host utama tidak dapat dilepas. Tetapkan host utama lain terlebih dahulu.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'fetch_platform_unsupported' => 'Pengambilan konten tidak didukung untuk platform :platform.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'fetch_channel_not_active' => 'Pengambilan konten memerlukan channel yang aktif.',
        'fetch_video_channel_mismatch' => 'Video :video bukan milik channel :channel.',
    ],
    'platform' => [
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'list_success' => 'Daftar platform berhasil dimuat.',
    ],
    'credential' => [
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'list_success' => 'Kredensial platform berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'show_success' => 'Kredensial platform berhasil dimuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'created' => 'Kredensial platform berhasil dibuat.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'updated' => 'Kredensial platform berhasil diperbarui.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'deleted' => 'Kredensial platform berhasil dihapus.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'revoked' => 'Kredensial platform berhasil dicabut.',
        'label_required' => 'Label kredensial wajib diisi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'label_taken' => 'Label kredensial ini sudah dipakai untuk platform tersebut.',
        'payload_invalid' => 'Payload rahasia kredensial tidak valid.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'payload_missing' => 'Kolom rahasia :field wajib diisi untuk tipe kredensial ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'payload_unknown_key' => 'Kolom rahasia :field tidak diizinkan untuk tipe kredensial ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'test_success' => 'Uji kredensial berhasil; kredensial kini aktif.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'test_missing_secret' => 'Rahasia kredensial belum lengkap untuk uji ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'test_unsupported' => 'Tidak ada probe uji untuk kombinasi platform dan tipe kredensial ini.',
        'test_not_allowed_status' => 'Kredensial yang dicabut tidak dapat diuji.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'test_youtube_ok' => 'Kunci API YouTube diterima.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'test_youtube_empty' => 'API YouTube tidak mengembalikan item channel untuk ID probe tersebut.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'test_twitch_ok' => 'App token Twitch berhasil dibuat dan probe Helix berhasil.',
        'test_bearer_ok' => 'Bearer token diterima probe platform.',
        'test_bearer_ok_no_probe' => 'Bearer token ada; probe live dilewati untuk platform ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'status_active_requires_test' => 'Gunakan tindakan uji untuk menandai kredensial aktif.',
        'revoke_requires_active' => 'Hanya kredensial aktif yang dapat dicabut.',
        'delete_requires_revoke' => 'Cabut kredensial ini sebelum menghapusnya.',
        'not_active' => 'Kredensial tidak aktif.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'app_token_oauth_only' => 'App access token hanya berlaku untuk kredensial oauth_app.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'app_token_twitch_only' => 'Pembuatan app access token hanya tersedia untuk Twitch.',
    ],
    // curated at 2026-09-27T19:20:00+07:00 — vTual incidents wave
    'incident' => [
        'list_success' => 'Insiden integrasi berhasil dimuat.',
        'show_success' => 'Insiden integrasi berhasil dimuat.',
        'status_updated' => 'Status insiden berhasil diperbarui.',
        'kinds' => [
            'websub' => 'WebSub YouTube',
            'eventsub' => 'EventSub Twitch',
            'eventsub_notification' => 'Pengiriman EventSub Twitch',
            'credential' => 'Kredensial platform',
            'platform_http' => 'API platform',
            'ingestion' => 'Ingest konten',
        ],
        'severities' => [
            'info' => 'Info',
            'warning' => 'Peringatan',
            'critical' => 'Kritis',
        ],
        'statuses' => [
            'open' => 'Terbuka',
            'acknowledged' => 'Diketahui',
            'resolved' => 'Terselesaikan',
        ],
    ],
    'policy' => [
        'denied' => 'Anda tidak diizinkan melakukan tindakan ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'access_denied' => 'Anda tidak dapat mengakses resource vTual ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'mutate_denied' => 'Anda tidak dapat mengubah resource vTual ini.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'archive_denied' => 'Hanya pemilik manajemen atau anggota admin yang dapat mengarsipkan manajemen ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'members_denied' => 'Anda tidak dapat mengelola anggota resource ini.',
        // curated at 2026-09-24T09:30:44+07:00 — id curation wave: glossary — management is manajemen, creator is kreator
        'assign_denied' => 'Anda tidak dapat menugaskan kreator untuk manajemen ini.',
    ],
    'integrations' => [
        'unsupported_operation' => 'Platform :platform tidak mendukung operasi :operation.',
        'platform_disabled' => 'Platform :platform dinonaktifkan atau tidak tersedia.',
        'platform_unknown' => 'Platform :platform tidak dikenal.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'no_active_credential' => 'Belum ada kredensial platform aktif untuk :platform. Buat dan uji terlebih dahulu.',
        'credential_required' => 'Klien platform tidak memiliki kredensial terikat.',
        'missing_api_key' => 'Kredensial YouTube tidak memiliki api_key.',
        'missing_client_id' => 'Kredensial Twitch tidak memiliki client_id.',
        'ids_required' => 'Minimal satu :label wajib diisi.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'batch_too_large' => 'Terlalu banyak :label (:count). Maksimum per permintaan adalah :max.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'batch_max_invalid' => 'Konfigurasi maksimum batch tidak valid.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'invalid_part' => 'Part YouTube :resource ":part" tidak diizinkan.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'parts_required' => 'Minimal satu part YouTube :resource wajib diisi.',
        'handle_required' => 'Handle atau login channel wajib diisi.',
        'youtube_channel_id_required' => 'ID channel YouTube valid (UC…) wajib diisi.',
        'search_event_type_invalid' => 'Tipe event pencarian harus live, upcoming, atau completed.',
        'feed_fetch_failed' => 'Permintaan feed YouTube gagal (HTTP :status).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'feed_parse_failed' => 'Respons feed YouTube tidak dapat diurai.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'upstream_error' => 'Permintaan platform :platform gagal (HTTP :status).',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'upstream_error_detail' => 'Permintaan platform :platform gagal (HTTP :status): :detail',
        'upstream_connection_error' => 'Koneksi ke platform :platform gagal.',
        // curated at 2026-09-25T21:30:00+07:00 — upstream retry/quota ops log lines
        'upstream_retry_scheduled' => 'Permintaan ke platform :platform gagal (HTTP :status); percobaan ulang ke-:attempt dalam :delay ms.',
        'upstream_quota_exhausted' => 'Kuota platform :platform habis (HTTP 429). Coba lagi setelah jendela kuota direset.',
        'probe_success' => 'Probe API platform selesai.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'probe_failed' => 'Probe API platform gagal tanpa terduga.',
        'probe_ids_required' => 'Minimal satu id wajib untuk method ini.',
        // curated at 2026-09-21T17:10:13+07:00 — id curation wave
        'probe_unknown_method' => 'Metode probe :method tidak dikenal.',
    ],
];
