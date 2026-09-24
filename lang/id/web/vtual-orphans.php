<?php

return [
    'title' => 'vTuber yatim',
    'breadcrumb' => 'vTuber yatim',
    'list_title' => 'Profil vTuber yatim',
    // curated at 2026-09-21T17:07:17+07:00 — id curation wave
    'subtitle' => 'Profil yang dibuat staf tanpa pemilik. Profil yang sudah diklaim tidak ditampilkan di sini.',
    'add_button' => 'Tambah vTuber yatim',
    'create_title' => 'Tambah vTuber yatim',
    // curated at 2026-09-21T17:07:17+07:00 — id curation wave
    'edit_title' => 'Ubah vTuber yatim',
    'form' => [
        'display_name' => 'Nama tampilan',
        'slug' => 'Slug',
        'slug_hint' => 'Opsional. Huruf kecil, angka, dan tanda hubung saja.',
        'bio' => 'Bio',
        'avatar_url' => 'URL avatar',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'submit_create' => 'Buat profil yatim',
        'submit_update' => 'Simpan perubahan',
    ],
    'datatable' => [
        'empty' => 'Belum ada profil vTuber yatim.',
        'zero_records' => 'Tidak ada profil yang cocok.',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'confirm_delete' => 'Arsipkan profil yatim ini? Profil ini tidak dapat diklaim lagi.',
        'columns' => [
            'display_name' => 'Nama tampilan',
            'slug' => 'Slug',
            'channels' => 'Channel',
            'created_at' => 'Dibuat',
            'actions' => 'Aksi',
        ],
    ],
    'channels' => [
        'section_title' => 'Channel platform',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'section_help' => 'Tambahkan profil channel untuk diresolusi secara batch (tanpa panggilan API langsung). Diresolusi otomatis saat 50 channel per platform terkumpul atau setelah 15 menit.',
        'avatar' => 'Avatar',
        'platform' => 'Platform',
        'profile' => 'Profil channel',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'profile_help' => 'ID channel platform, @handle, login, atau URL profil.',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'profile_help_youtube' => 'YouTube: ID channel UC, @handle, atau URL profil youtube.com/…',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'profile_help_twitch' => 'Twitch: ID pengguna numerik, login, atau URL profil twitch.tv/…',
        'profile_placeholder_youtube' => 'UC… / @handle / https://youtube.com/@handle',
        'profile_placeholder_twitch' => '12345678 / login / https://twitch.tv/login',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'profile_or_id' => 'Profil / ID eksternal',
        'handle' => 'Handle',
        'status' => 'Status',
        'add_button' => 'Tambah channel',
        'table_caption' => 'Channel vTuber yatim',
        'empty' => 'Belum ada channel terdaftar.',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'status_pending' => 'Menunggu resolusi',
        'status_active' => 'Aktif',
        // curated at 2026-09-21T17:07:17+07:00 — id curation wave
        'status_disconnected' => 'Terputus',
        'status_archived' => 'Diarsipkan',
    ],
];
