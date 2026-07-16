<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription grace after period end
    |--------------------------------------------------------------------------
    | During grace: no new uploads for File Hosting unlimited products; files kept.
    | After grace: free-tier quota enforced; excess files purged (oldest retained).
    */
    'grace_days' => (int) env('SERVICE_GRACE_DAYS', 7),

    'settlement_currency' => env('SERVICE_SETTLEMENT_CURRENCY', 'IDR'),
];
