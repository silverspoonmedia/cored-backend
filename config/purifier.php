<?php

/*
|--------------------------------------------------------------------------
| HTMLPurifier configuration
|--------------------------------------------------------------------------
|
| The `blog` profile whitelists exactly the markup produced by the SPA
| Tiptap editor (headings, lists, links, images, blockquotes, code).
| Anything outside this set is stripped server-side before storage, since
| post content is later rendered with `v-html` in the admin UI.
|
*/

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,
        ],

        'blog' => [
            'HTML.Doctype' => 'XHTML 1.0 Transitional',
            'HTML.Allowed' => implode(',', [
                'h1,h2,h3,h4,h5,h6',
                'p[style],br,hr',
                'b,strong,i,em,u,sub,sup,span[style]',
                'a[href|title|target|rel]',
                'img[src|alt|width|height]',
                'ul,ol,li',
                'blockquote',
                'pre,code',
            ]),
            'CSS.AllowedProperties' => 'text-align',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
            'Attr.AllowedFrameTargets' => ['_blank', '_self'],
        ],
    ],
];
