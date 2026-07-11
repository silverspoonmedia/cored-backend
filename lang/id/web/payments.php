<?php

$payments = require __DIR__.'/../../en/web/payments.php';

$payments['checkout']['select_channel'] = 'Metode pembayaran';
$payments['checkout']['pay_with_channel'] = 'Lanjut ke pembayaran';
$payments['checkout']['change_channel'] = 'Ganti channel pembayaran';
$payments['checkout']['confirm_paid'] = 'Saya sudah bayar';
$payments['checkout']['no_channels'] = 'Belum ada metode pembayaran yang tersedia untuk invoice ini.';

return $payments;
