<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

$disk = config('blog.media.disk');
echo "disk: {$disk}\n";

$storage = Storage::disk($disk);
$path = 'blog/probe-'.bin2hex(random_bytes(4)).'.png';

// 1x1 transparent PNG
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
$storage->put($path, $png);

$url = $storage->url($path);
echo "url: {$url}\n";

$response = Http::timeout(20)->get($url);
echo "http_status: {$response->status()}\n";
echo "content_type: {$response->header('Content-Type')}\n";
echo 'body_matches: '.var_export($response->body() === $png, true)."\n";

$storage->delete($path);
echo "cleaned_up: ".var_export(! $storage->exists($path), true)."\n";
