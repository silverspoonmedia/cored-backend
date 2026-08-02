<?php

return [
    'title' => 'Pengaturan',
    'subtitle' => 'Konfigurasi operasional yang dikelola melalui UI admin.',
    'read_only' => 'Anda hanya memiliki akses lihat untuk pengaturan ini.',
    'source' => 'Sumber saat ini: :source',
    'groups' => [
        'platform' => 'Platform',
        'file_hosting' => 'File Hosting',
        'microstock' => 'Microstock',
        'vtual' => 'vTual',
        'notification_audiences' => 'Audiens notifikasi',
        'currencies' => 'Mata uang',
    ],
    'fields' => [
        'platform.jwt.ttl' => 'TTL akses JWT (menit)',
        'platform.hcaptcha.enabled' => 'hCaptcha aktif',
    ],
    'hints' => [],
];
