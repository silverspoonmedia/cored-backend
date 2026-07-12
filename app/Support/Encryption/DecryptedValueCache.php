<?php

namespace App\Support\Encryption;

use Illuminate\Support\Facades\Cache;

class DecryptedValueCache
{
    public function remember(string $contextKey, callable $resolver): mixed
    {
        $cacheKey = $this->cacheKey($contextKey);
        $ttl = (int) config('encryption.decrypted_cache_ttl', 900);

        return Cache::remember($cacheKey, $ttl, $resolver);
    }

    public function forget(string $contextKey): void
    {
        Cache::forget($this->cacheKey($contextKey));
    }

    public function flushAll(): void
    {
        // POC uses key-per-record TTL cache; full flush is store-dependent.
        // Artisan command documents limitation; Redis SCAN flush deferred to Phase 2.
    }

    private function cacheKey(string $contextKey): string
    {
        return (string) config('encryption.decrypted_cache_prefix', 'encryption.decrypted').'.'.$contextKey;
    }
}
