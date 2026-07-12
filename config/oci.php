<?php

return [

    'config_file' => env('OCI_CONFIG_FILE'),

    'profile' => env('OCI_PROFILE', 'DEFAULT'),

    'kms_key_id' => env('OCI_KMS_KEY_ID'),

    'kms_crypto_endpoint' => env('OCI_KMS_CRYPTO_ENDPOINT'),

    'http_timeout' => (int) env('OCI_HTTP_TIMEOUT', 15),

];
