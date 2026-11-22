<?php

namespace App\Domain\Archivd\Support;

use App\Support\SpaPublicUrl;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * Browser-facing URLs for Archivd's public shares.
 *
 * Public sharing is a product capability: the guest pages (`/s/f/{id}`,
 * `/s/d/{id}`) exist only because Archivd owns them, so the kernel's
 * {@see SpaPublicUrl} keeps the platform links (reset, verify, payment) and this
 * class owns the share links. Strategic the same rule applies — always the SPA
 * origin, never a foreign host.
 */
final class PublicShareUrl
{
    public static function file(string $fileId): string
    {
        return self::share('f', 'archivd.public.files.show', 'file', $fileId);
    }

    public static function folder(string $folderId): string
    {
        return self::share('d', 'archivd.public.folders.show', 'folder', $folderId);
    }

    private static function share(string $segment, string $fallbackRoute, string $parameter, string $id): string
    {
        $safeId = self::assertSafeSegment($id);

        if (SpaPublicUrl::origin() === null && Route::has($fallbackRoute)) {
            return route($fallbackRoute, [$parameter => $safeId]);
        }

        return SpaPublicUrl::to('/s/'.$segment.'/'.$safeId);
    }

    private static function assertSafeSegment(string $segment): string
    {
        if ($segment === '' || str_contains($segment, '/') || str_contains($segment, '\\') || str_contains($segment, '..') || str_contains($segment, ':') || str_contains($segment, '?') || str_contains($segment, '#')) {
            throw new InvalidArgumentException('Invalid SPA public URL segment.');
        }

        return $segment;
    }
}
