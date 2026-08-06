<?php

namespace App\Domain\Vtual\Support;

use Illuminate\Support\Str;

final class SlugGenerator
{
    /**
     * Build a URL-safe slug from a display name. Empty after slugify becomes "item".
     */
    public static function from(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'item';
    }

    /**
     * Ensure uniqueness against an existing-slug checker.
     *
     * @param  callable(string): bool  $exists
     */
    public static function unique(string $base, callable $exists): string
    {
        $slug = self::from($base);
        $candidate = $slug;
        $i = 2;

        while ($exists($candidate)) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * Build a unique slug with a random lowercase alphanumeric prefix.
     * Format: {random6}-{slugified-base} (with numeric suffix on collision).
     *
     * @param  callable(string): bool  $exists
     */
    public static function uniquePrefixed(string $base, callable $exists, int $prefixLength = 6): string
    {
        $slug = self::from($base);
        $candidate = self::randomPrefix($prefixLength).'-'.$slug;
        $i = 2;

        while ($exists($candidate)) {
            $candidate = self::randomPrefix($prefixLength).'-'.$slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    private static function randomPrefix(int $length): string
    {
        return Str::lower(Str::random($length));
    }
}
