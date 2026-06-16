<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active web layout
    |--------------------------------------------------------------------------
    |
    | Names the Blade directory under resources/views/{layout}/ and the
    | anonymous component prefix (<x-{layout}::…>). Web controllers resolve
    | screens via webView('dashboard') → view('{layout}.dashboard').
    |
    */

    'layout' => env('WEB_LAYOUT', 'adminlte4'),

];
