<?php

$payments = require __DIR__.'/../../en/web/payments.php';

$payments['checkout']['select_channel'] = 'Metode pembayaran';
$payments['checkout']['pay_with_channel'] = 'Lanjut ke pembayaran';
$payments['checkout']['change_channel'] = 'Ganti channel pembayaran';
$payments['checkout']['confirm_paid'] = 'Saya sudah bayar';
$payments['checkout']['confirm_paid_help'] = 'Jika Anda sudah menyelesaikan pembayaran di luar layar ini (mis. tunai atau transfer bank), klik di sini untuk memeriksa ulang status invoice.';
$payments['checkout']['no_channels'] = 'Belum ada metode pembayaran yang tersedia untuk invoice ini.';
$payments['channels']['priority_autosave_hint'] = 'tersimpan otomatis';

return $payments;
