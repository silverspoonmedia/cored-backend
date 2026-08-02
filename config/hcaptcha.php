<?php

return [

    'enabled' => false,

    'secret' => env('HCAPTCHA_SECRET'),

    'sitekey' => env('HCAPTCHA_SITEKEY'),

    'verify_url' => 'https://api.hcaptcha.com/siteverify',

];
