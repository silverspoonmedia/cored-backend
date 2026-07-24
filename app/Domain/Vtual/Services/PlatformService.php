<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PlatformService
{
    /**
     * Seed default platforms (idempotent). Kick is present but disabled.
     */
    public function seedDefaults(): void
    {
        DB::transaction(function (): void {
            foreach (PlatformKey::cases() as $key) {
                Platform::query()->updateOrCreate(
                    ['key' => $key->value],
                    [
                        'name' => $key->defaultName(),
                        'enabled' => $key->enabledByDefault(),
                        'supports_live' => true,
                        'supports_vod' => $key->supportsVodByDefault(),
                    ],
                );
            }
        });
    }

    /**
     * @return Collection<int, Platform>
     */
    public function listAll(): Collection
    {
        return Platform::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Platform>
     */
    public function listEnabled(): Collection
    {
        return Platform::query()->where('enabled', true)->orderBy('name')->get();
    }
}
