<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription grace after period end
    |--------------------------------------------------------------------------
    | During grace: no new uploads for File Hosting unlimited products; files kept.
    | After grace: free-tier quota enforced; excess files purged (oldest retained).
    */
    'grace_days' => 7,

    'settlement_currency' => 'IDR',

  /*
  |--------------------------------------------------------------------------
  | Purchase hub quantity (addon plans, pending hub only)
  |--------------------------------------------------------------------------
  */
    'purchase' => [
        'max_quantity' => 99,
    ],
];
