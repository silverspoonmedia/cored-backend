<?php

namespace App\Domain\Vtual\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Forever cache for the guest-safe /vtual landing payload.
 *
 * Cache policy: rebuild on miss, flush on underlying data change
 * (content/creator/channel writes + vtual.landing.* settings). Primitive
 * array payload only — no Eloquent models in the forever key.
 */
class PublicLandingCache
{
    public const KEY = 'vtual.public.landing';

    /**
     * @param  callable(): array<string, mixed>  $builder
     * @return array<string, mixed>
     */
    public static function remember(callable $builder): array
    {
        $payload = Cache::get(self::KEY);

        if (is_array($payload)) {
            return $payload;
        }

        $payload = $builder();
        Cache::forever(self::KEY, $payload);

        return $payload;
    }

    public static function flush(): void
    {
        Cache::forget(self::KEY);
    }
}
