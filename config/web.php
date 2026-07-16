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

    /*
    |--------------------------------------------------------------------------
    | Intended URL exclusions (post-login redirect)
    |--------------------------------------------------------------------------
    |
    | Route names that must not be stored as url.intended when a guest hits
    | web.auth. Background JSON/sub-resource endpoints (notification bell,
    | DataTables, navigation tree) are excluded so they cannot overwrite the
    | full-page URL the user originally requested.
    |
    */

    'intended_url_exclude_routes' => [
        'notifications.unread-count',
        'notifications.recent',
        'notifications.read',
        'notifications.read-all',
        'users.datatable',
        'navigation.tree',
    ],

];
