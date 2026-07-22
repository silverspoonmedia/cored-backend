<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\PlatformKey;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasUuids;

    protected $table = 'vtual_platforms';

    protected $fillable = [
        'key',
        'name',
        'enabled',
        'supports_live',
        'supports_vod',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => PlatformKey::class,
            'enabled' => 'boolean',
            'supports_live' => 'boolean',
            'supports_vod' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Channel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class, 'platform_id');
    }

    /**
     * @return HasMany<PlatformCredential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(PlatformCredential::class, 'platform_id');
    }

    public function isEnabled(): bool
    {
        return $this->enabled === true;
    }
}
