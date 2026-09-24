<?php

declare(strict_types=1);

return [
    // Gate / middleware
    'auth_required' => 'Autentikasi diperlukan.',
    'unknown_product' => 'Domain produk tidak dikenal: :product.',
    // curated at 2026-09-21T17:00:35+07:00 — id curation wave
    'forbidden' => 'Domain produk ini bersifat privat. Akses dibatasi untuk Root, Admin, dan penguji betanya.',

    // Admin API
    // curated at 2026-09-21T17:00:35+07:00 — id curation wave
    'list_success' => 'Pengaturan akses domain produk berhasil dimuat.',
    // curated at 2026-09-21T17:00:35+07:00 — id curation wave
    'updated' => 'Visibilitas domain produk berhasil diperbarui.',
    // curated at 2026-09-21T17:00:35+07:00 — id curation wave
    'invalid_visibility' => 'Visibilitas harus publik atau privat.',

    // UI
    'title' => 'Akses Domain Produk',
    // curated at 2026-09-21T17:00:35+07:00 — id curation wave
    'subtitle' => 'Atur domain produk mana yang publik atau dibatasi untuk penguji beta.',
    'empty' => 'Belum ada domain produk terdaftar.',
    'col_domain' => 'Domain Produk',
    'col_visibility' => 'Visibilitas',
    'visibility_public' => 'Publik',
    'visibility_private' => 'Privat',
    'public_hint' => 'Siapa pun dapat mengakses domain produk ini.',
    // curated at 2026-09-21T17:00:35+07:00 — id curation wave
    'private_hint' => 'Hanya Root, Admin, dan penguji beta yang dapat mengakses.',
    'beta_role' => 'Peran beta: :role',
    // curated at 2026-09-21T17:00:35+07:00 — id curation wave
    'toggle_updated' => 'Visibilitas berhasil diperbarui.',
    // curated at 2026-09-21T17:00:35+07:00 — id curation wave
    'toggle_failed' => 'Visibilitas tidak dapat diperbarui.',
];
