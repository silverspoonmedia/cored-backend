@php
    /**
     * Maps a MIME type to a Bootstrap Icon class and fh-icon color modifier.
     *
     * @param  string|null  $mime
     * @param  bool  $isFolder
     */
    $isFolder = $isFolder ?? false;
    $mime = $mime ?? null;
    $mimeLower = strtolower($mime ?? '');

    $icon = match (true) {
        $isFolder => ['icon' => 'bi-folder-fill', 'color' => 'fh-icon--folder'],
        str_starts_with($mimeLower, 'image/') => ['icon' => 'bi-file-earmark-image', 'color' => 'fh-icon--image'],
        $mimeLower === 'application/pdf' => ['icon' => 'bi-file-earmark-pdf', 'color' => 'fh-icon--pdf'],
        str_starts_with($mimeLower, 'video/') => ['icon' => 'bi-file-earmark-play', 'color' => 'fh-icon--video'],
        str_starts_with($mimeLower, 'audio/') => ['icon' => 'bi-file-earmark-music', 'color' => 'fh-icon--audio'],
        in_array($mimeLower, ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip']) => ['icon' => 'bi-file-earmark-zip', 'color' => 'fh-icon--archive'],
        in_array($mimeLower, ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']) => ['icon' => 'bi-file-earmark-spreadsheet', 'color' => 'fh-icon--sheet'],
        in_array($mimeLower, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain']) => ['icon' => 'bi-file-earmark-text', 'color' => 'fh-icon--doc'],
        in_array($mimeLower, ['application/json', 'text/html', 'text/css', 'application/xml', 'text/xml', 'application/javascript', 'text/javascript', 'application/x-httpd-php']) => ['icon' => 'bi-file-earmark-code', 'color' => 'fh-icon--code'],
        default => ['icon' => 'bi-file-earmark', 'color' => 'fh-icon--file'],
    };
@endphp
<i class="bi {{ $icon['icon'] }} {{ $icon['color'] }}"></i>
