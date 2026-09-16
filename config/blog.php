<?php

// Blog domain configuration: media storage target for post images
// (thumbnails + inline editor images).
return [
    'media' => [
        'disk' => env('BLOG_MEDIA_DISK', 'public'),
        'directory' => env('BLOG_MEDIA_DIRECTORY', 'blog'),
        'max_size_kb' => (int) env('BLOG_MEDIA_MAX_SIZE_KB', 4096),
    ],
];
