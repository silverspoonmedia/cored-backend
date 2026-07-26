<?php

namespace App\Domain\Microstock\Models;

use App\Domain\Identity\Models\User;
use App\Support\Encryption\Casts\EnvelopeEncryptedArray;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCredential extends Model
{
    use HasUuids;

    public const ENCRYPTION_RESOURCE_TYPE = 'microstock_marketplace_credentials';

    public const ENCRYPTION_FIELD_SECRET = 'secret_payload';

    public const ENCRYPTION_PURPOSE = 'microstock';

    protected $table = 'microstock_marketplace_credentials';

    /**
     * Never mass-assign the encrypted payload from request bags.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'marketplace',
        'label',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret_payload' => EnvelopeEncryptedArray::class.':'
                .self::ENCRYPTION_RESOURCE_TYPE.','
                .self::ENCRYPTION_FIELD_SECRET.','
                .self::ENCRYPTION_PURPOSE,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
