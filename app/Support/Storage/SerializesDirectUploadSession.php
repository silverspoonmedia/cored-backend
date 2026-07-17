<?php

namespace App\Support\Storage;

/**
 * Shared JSON shape for direct-upload session responses (init + status).
 *
 * Controllers may inject extra keys (e.g. folder_id) via {@see $extra}.
 */
trait SerializesDirectUploadSession
{
    /**
     * @param  array{
     *     upload: object{id: mixed, path: string, expires_at?: mixed, folder_id?: mixed},
     *     strategy: string,
     *     chunk_size: int,
     *     part_count: int,
     *     parts: list<array<string, mixed>>,
     *     recorded_parts?: list<array<string, mixed>>
     * }  $result
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function serializeUploadSession(
        array $result,
        bool $includeRecorded = false,
        array $extra = [],
    ): array {
        $upload = $result['upload'];

        $payload = array_merge([
            'upload_id' => $upload->id,
            'strategy' => $result['strategy'],
            'path' => $upload->path,
            'chunk_size' => $result['chunk_size'],
            'part_count' => $result['part_count'],
            'parts' => $result['parts'],
            'expires_at' => $upload->expires_at?->toIso8601String(),
        ], $extra);

        if ($includeRecorded) {
            $payload['recorded_parts'] = $result['recorded_parts']
                ?? (method_exists($upload, 'recordedParts') ? $upload->recordedParts() : []);
        }

        return $payload;
    }
}
