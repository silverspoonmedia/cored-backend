<?php

use App\Support\Oci\OciConfigPath;

return [

    // Derived from APP_ENV, not set per environment: production reads
    // .oci/production/, everything else reads .oci/testing/. See OciConfigPath.
    'config_file' => OciConfigPath::configFile(env('APP_ENV')),

    'profile' => env('OCI_PROFILE', 'DEFAULT'),

    'kms_key_id' => env('OCI_KMS_KEY_ID'),

    'kms_crypto_endpoint' => env('OCI_KMS_CRYPTO_ENDPOINT'),

    'http_timeout' => (int) env('OCI_HTTP_TIMEOUT', 15),

];
