<?php

return [
    'gateways' => [
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'title' => 'Gateway pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'list_title' => 'Daftar gateway pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'create' => 'Tambah gateway',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'create_title' => 'Buat gateway pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'edit_title' => 'Ubah gateway pembayaran',
        'form' => [
            'name' => 'Nama sistem',
            'display_name' => 'Nama tampilan',
            'priority' => 'Prioritas',
            'is_active' => 'Aktif',
        ],
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'confirm_delete' => 'Hapus gateway pembayaran ini?',
        'datatable' => [
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'empty' => 'Belum ada gateway pembayaran yang dikonfigurasi.',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'zero_records' => 'Tidak ada gateway pembayaran yang cocok dengan pencarian Anda.',
            'columns' => [
                'name' => 'Nama',
                'display_name' => 'Nama tampilan',
                'is_active' => 'Aktif',
                'priority' => 'Prioritas',
            ],
        ],
    ],

    'show' => [
        'payment_not_started' => 'Metode pembayaran belum dipilih',
        'payment_pending' => 'Menunggu pembayaran',
        'status' => [
            'paid' => 'Lunas',
            'expired' => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan',
            'failed' => 'Gagal',
        ],
        'payment_steps' => [
            'aria' => 'Langkah pembayaran',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'choose_channel' => 'Pilih kanal',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'choose_channel_hint' => 'Pilih QRIS, transfer bank, atau dompet digital.',
            'pay' => 'Bayar',
            'pay_hint' => 'Selesaikan pembayaran sesuai instruksi yang ditampilkan.',
            'confirm' => 'Konfirmasi',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'confirm_hint' => 'Status diperbarui setelah penyedia pembayaran mengonfirmasi pembayaran.',
        ],
    ],

    'invoices' => [
        'title' => 'Invoice',
        'list_title' => 'Daftar invoice',
        'show_title' => 'Detail invoice',
        // curated at 2026-09-23T12:37:01+07:00 — ko curation wave: id invoice card title aligned with the SPA
        'show_card_title' => 'Invoice No. :number',
        'view_order' => 'Lihat pesanan',
        'view_purchase' => 'Lihat pembelian layanan',
        'back_to_list' => 'Kembali ke daftar invoice',
        'datatable' => [
            'empty' => 'Belum ada invoice.',
            'zero_records' => 'Tidak ada invoice yang cocok dengan pencarian Anda.',
            'columns' => [
                'invoice_number' => 'No. invoice',
                'status' => 'Status',
                'subtotal' => 'Subtotal',
                // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                'fee' => 'Biaya pembayaran',
                // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                'total' => 'Total pembeli',
                'created_at' => 'Dibuat',
                'actions' => 'Aksi',
            ],
        ],
        'export_csv' => 'Ekspor CSV',
        'fields' => [
            'invoice_number' => 'Nomor invoice',
            'status' => 'Status',
            'payment_method' => 'Metode pembayaran',
            'gateway' => 'Gateway',
            'subtotal' => 'Harga layanan',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'fee' => 'Biaya pembayaran (pembeli)',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'total' => 'Total pembeli',
            'currency' => 'Mata uang',
            'paid_at' => 'Dibayar pada',
            'expired_at' => 'Kedaluwarsa pada',
            'created_at' => 'Dibuat pada',
            'payment_route' => 'Rute checkout',
        ],
    ],

    'income' => [
        'title' => 'Pendapatan pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'subtitle' => 'Ringkasan pendapatan dan kas masuk dari invoice lunas. Harga layanan adalah pendapatan produk Anda; biaya pembayaran adalah tambahan dari pembeli yang diteruskan ke penyedia pembayaran.',
        'loading' => 'Memuat…',
        'load_failed' => 'Tidak dapat memuat data pendapatan.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'empty_invoices' => 'Belum ada invoice lunas pada periode ini.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'empty_breakdown' => 'Belum ada data untuk periode ini.',
        'invoices_title' => 'Invoice lunas',
        'pagination' => 'Halaman :page dari :total',
        'filters' => [
            'from' => 'Dari',
            'to' => 'Sampai',
            'basis' => 'Dasar tanggal',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'basis_paid_at' => 'Tanggal pembayaran (paid_at)',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'basis_withdrawable_at' => 'Tanggal pencairan (withdrawable_at)',
            'gateway' => 'Gateway',
            'all_gateways' => 'Semua gateway',
            'apply' => 'Terapkan',
        ],
        'summary' => [
            'service_revenue' => 'Pendapatan layanan',
            'service_revenue_hint' => 'Jumlah subtotal invoice (penjualan produk Anda).',
            'buyer_fees' => 'Biaya pembayaran pembeli',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'buyer_fees_hint' => 'Biaya tambahan kanal yang dibebankan ke pelanggan.',
            'cash_collected' => 'Kas masuk',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'cash_collected_hint' => 'Total yang dibayar pelanggan (harga layanan + biaya).',
            'invoice_count' => 'Invoice lunas',
            'avg_order_value' => 'Rata-rata nilai pesanan',
        ],
        'pipeline' => [
            'title' => 'Alur dana',
            'invoices' => 'invoice',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'awaiting_settlement' => 'Menunggu penyelesaian penyedia',
            'ready_to_disburse' => 'Siap dicairkan',
            'in_payout' => 'Dalam pencairan',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'disbursed' => 'Sudah dicairkan ke bank',
            'failed_payout' => 'Pencairan gagal',
        ],
        'breakdown' => [
            'gateway' => 'Per gateway',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'channel' => 'Per kanal',
            'product' => 'Per jenis produk',
            'label' => 'Label',
            'invoices' => 'Invoice',
            'service_revenue' => 'Pendapatan layanan',
            'cash_collected' => 'Kas masuk',
        ],
        'daily' => [
            'title' => 'Tren harian',
            'date' => 'Tanggal',
            'invoices' => 'Invoice',
            'service_revenue' => 'Pendapatan layanan',
            'cash_collected' => 'Kas masuk',
        ],
    ],

    'subscriptions' => [
        'title' => 'Langganan',
        'list_title' => 'Daftar langganan',
        'create_title' => 'Tambah langganan',
        'edit_title' => 'Edit langganan',
        'show_title' => 'Detail langganan',
        'show_card_title' => 'Langganan :number',
        'add_button' => 'Tambah langganan',
        'new_card_title' => 'Langganan baru',
        'edit_card_title' => 'Edit langganan: :number',
        'back_to_list' => 'Kembali ke daftar langganan',
        'datatable' => [
            'empty' => 'Belum ada langganan.',
            'zero_records' => 'Tidak ada langganan yang cocok dengan pencarian Anda.',
            'columns' => [
                'subscription_number' => 'No. langganan',
                'subscriber_name' => 'Pelanggan',
                // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                'plan_name' => 'Paket',
                'status' => 'Status',
                'amount' => 'Jumlah',
                'actions' => 'Aksi',
            ],
        ],
        'fields' => [
            'subscriber_name' => 'Nama pelanggan',
            'subscriber_email' => 'Email pelanggan',
            'subscriber_phone' => 'Telepon pelanggan',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'plan_name' => 'Nama paket',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'plan_description' => 'Deskripsi paket',
            'billing_cycle' => 'Siklus penagihan',
            'amount' => 'Jumlah',
            'currency' => 'Mata uang',
            'status' => 'Status',
            'auto_renew' => 'Perpanjangan otomatis',
            'started_at' => 'Dimulai pada',
            'expires_at' => 'Berakhir pada',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'trial_ends_at' => 'Uji coba berakhir pada',
            'created_at' => 'Dibuat pada',
        ],
    ],

    'confirm' => [
        'delete_subscription' => 'Hapus langganan ini?',
    ],

    'ipaymu_return' => [
        'invoice_not_found' => 'Pembayaran ini tidak dapat dicocokkan dengan invoice mana pun.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'paid' => 'Pembayaran diterima. Invoice Anda ditandai lunas.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'pending' => 'Pembayaran sedang diproses. Muat ulang halaman sesaat lagi bila status belum berubah.',
        'failed' => 'Pembayaran gagal atau ditolak.',
        'cancelled' => 'Pembayaran dibatalkan.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'expired' => 'Sesi pembayaran sudah kedaluwarsa.',
    ],

    'checkout' => [
        'title' => 'Pembayaran',
        'invoice_summary' => 'Invoice No. :number',
        'amount' => 'Jumlah tagihan',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'subtotal' => 'Harga layanan',
        'payment_fee' => 'Biaya pembayaran',
        'payment_fee_note' => 'Dibayarkan ke penyedia pembayaran dan ditambahkan ke total Anda.',
        'total_due' => 'Total dibayar',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'fee_estimate_hint' => 'Estimasi biaya — jumlah akhir dikonfirmasi saat Anda melanjutkan ke pembayaran.',
        'fee_percent_basis' => ':percent dari harga layanan',
        'fee_at_checkout_hint' => 'Biaya penyedia pembayaran ditambahkan saat Anda memilih metode pembayaran.',
        'routing_updated' => 'Metode pembayaran diperbarui. Biaya mungkin berubah.',
        'channel_unavailable' => ':channel tidak lagi tersedia.',
        'fee_updated' => 'Biaya :channel diperbarui dari :old menjadi :new.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'selected_channel_unavailable' => 'Metode pembayaran yang Anda pilih tidak lagi tersedia. Pilih metode lain.',
        'pay_now' => 'Bayar sekarang',
        'snap_loading' => 'Membuka pembayaran…',
        'snap_failed' => 'Tidak dapat memulai checkout',
        'started' => 'Sesi checkout dibuat.',
        'paid' => 'Invoice ini sudah lunas.',
        'not_payable' => 'Invoice ini tidak dapat dibayar dalam status saat ini.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'unsupported_gateway' => 'Gateway pembayaran invoice ini tidak mendukung checkout terkelola.',
        'select_channel' => 'Metode pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'cheap_alt_hint' => 'Bayar dengan :channel untuk menghemat biaya (pindai QR saat membayar).',
        'pay_with_channel' => 'Lanjut ke pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'channel_loading' => 'Membuat pembayaran…',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'channel_ready' => 'Selesaikan pembayaran dengan instruksi di bawah.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'channel_failed' => 'Checkout kanal tidak dapat dimulai',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'confirm_paid' => 'Saya sudah membayar',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'confirm_paid_help' => 'Jika Anda sudah menyelesaikan pembayaran di luar layar ini (misalnya tunai atau transfer bank), klik di sini untuk memeriksa ulang status invoice.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'change_channel' => 'Ganti kanal pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'no_channels' => 'Belum ada metode pembayaran yang tersedia untuk invoice ini.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'permission_required' => 'Anda tidak berwenang memulai pembayaran. Minta izin “initiate payment” kepada administrator.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'synced' => 'Status pembayaran berhasil disinkronkan.',
        'syncing' => 'Menyinkronkan…',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'sync_pending' => 'Pembayaran masih tertunda.',
        'sync_failed' => 'Tidak dapat menyinkronkan status pembayaran.',
    ],

    'instructions' => [
        'title' => 'Instruksi pembayaran',
        'expires_at' => 'Bayar sebelum :time',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'qr_string' => 'Data QR',
        'bank' => 'Bank',
        'va_number' => 'Nomor virtual account',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'account_name' => 'Nama pemilik rekening',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'open_ewallet' => 'Buka aplikasi dompet digital',
        'copy' => 'Salin',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'copied' => 'Tersalin ke papan klip',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'copy_failed' => 'Gagal menyalin',
        'pending_hint' => 'Status diperbarui otomatis setelah penyedia pembayaran mengonfirmasi pembayaran Anda.',
    ],

    'channels' => [
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'title' => 'Kanal pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'list_title' => 'Daftar kanal pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'updated' => 'Kanal pembayaran berhasil diperbarui.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'route_updated' => 'Rute kanal pembayaran berhasil diperbarui.',
        'routes_title' => 'Rute gateway',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'routes_lead' => 'Checkout mencoba rute utama atau tunggal lebih dahulu, lalu rute cadangan. Dalam peran yang sama, angka prioritas lebih kecil dicoba lebih dahulu. Rute nonaktif atau kredensial gateway yang belum tersedia akan dilewati.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'empty' => 'Belum ada kanal pembayaran yang dikonfigurasi. Jalankan php artisan payments:sync-routing-policy setelah PaymentGatewaySeeder.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'no_routes' => 'Belum ada rute gateway yang terikat ke kanal ini.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'no_results' => 'Tidak ada kanal pembayaran yang cocok dengan pencarian.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'search_label' => 'Cari kanal pembayaran',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'search_placeholder' => 'Cari berdasarkan nama, kode, atau jenis…',
        'inactive_channel' => 'Disembunyikan dari checkout',
        'primary_route' => 'Dicoba lebih dulu',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'priority_hint' => 'Angka lebih kecil = prioritas lebih tinggi dalam peran rute yang sama. Prioritas yang dikelola kebijakan tidak dapat diubah di sini.',
        'priority_autosave_hint' => 'tersimpan otomatis',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'visible_count' => '{0} Tidak ada kanal|{1} :count kanal terlihat|[2,*] :count kanal terlihat',
        'route_count' => '{0} Tidak ada rute|{1} :count rute|[2,*] :count rute',
        'status' => [
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
        ],
        'kinds' => [
            'qris' => 'QRIS',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'va' => 'Akun virtual',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'ewallet' => 'Dompet digital',
        ],
        'intro' => [
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'title' => 'Cara kerja perutean checkout kanal pembayaran',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'lead' => 'Pelanggan memilih kanal logis (QRIS, VA bank, dompet digital). Monolith lalu mencoba rute gateway sesuai kebijakan (peran rute, lalu prioritas) hingga instruksi pembayaran terbentuk.',
            'steps' => [
                [
                    // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                    'title' => 'Aktifkan kanal',
                    // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                    'body' => 'Kanal nonaktif disembunyikan dari checkout. Rute tetap dapat dikonfigurasi selama kanal dimatikan.',
                ],
                [
                    // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                    'title' => 'Pahami peran rute',
                    // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                    'body' => 'Rute utama atau tunggal dicoba sebelum rute cadangan. Prioritas (10 sebelum 20) berlaku dalam peran yang sama. Biaya dan settlement mengikuti Routing_Price.yaml.',
                ],
                [
                    // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                    'title' => 'Fallback saat checkout',
                    // curated at 2026-09-21T17:05:34+07:00 — id curation wave
                    'body' => 'Jika semua rute yang layak gagal atau kredensialnya belum tersedia, checkout dapat menawarkan Snap terkelola atau pengalihan iPaymu.',
                ],
            ],
        ],
        'stats' => [
            // curated at 2026-09-21T17:06:40+07:00 — id curation wave
            'channels_active' => 'Kanal aktif',
            // curated at 2026-09-21T17:06:40+07:00 — id curation wave
            'routes_active' => 'Rute aktif',
            'checkout_hint' => 'Perilaku checkout',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'checkout_body' => 'Hanya kanal aktif dengan minimal satu rute yang layak yang ditampilkan ke pembayar.',
        ],
        'columns' => [
            'code' => 'Kode',
            'display_name' => 'Nama tampilan',
            'kind' => 'Jenis',
            'sort_order' => 'Urutan',
            'is_active' => 'Aktif',
        ],
        'route_columns' => [
            'gateway' => 'Gateway',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'provider_type' => 'Jenis penyedia',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'provider_channel' => 'Kanal penyedia',
            'priority' => 'Prioritas',
            'is_active' => 'Aktif',
            'route_role' => 'Peran',
            'settlement' => 'Settlement',
            'fee' => 'Biaya',
            // curated at 2026-09-21T17:05:34+07:00 — id curation wave
            'policy_managed' => 'Dikelola kebijakan',
        ],
        'route_roles' => [
            'primary' => 'Utama',
            'fallback' => 'Cadangan',
            'only' => 'Tunggal',
        ],
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'sync_policy' => 'Sinkronkan kebijakan perutean',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'sync_policy_hint' => 'Muat ulang kanal dan rute dari Routing_Price.yaml.',
        // curated at 2026-09-21T17:05:34+07:00 — id curation wave
        'sync_policy_done' => 'Kebijakan perutean disinkronkan (:channels kanal, :routes rute). Memuat ulang…',
    ],
];
