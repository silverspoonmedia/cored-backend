<?php

return [

    'enabled' => (bool) env('HCAPTCHA_ENABLED', false),

    'secret' => env('HCAPTCHA_SECRET'),

    'sitekey' => env('HCAPTCHA_SITEKEY'),

    'verify_url' => 'https://api.hcaptcha.com/siteverify',

];
