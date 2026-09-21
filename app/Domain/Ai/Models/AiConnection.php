<?php

namespace App\Domain\Ai\Models;

use App\Domain\Ai\Enums\AiConnectionDriver;
use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Identity\Models\User;
use App\Support\Encryption\Casts\EnvelopeEncryptedArray;
use App\Support\Encryption\DecryptionContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central AI transport + credentials row (Tier C — envelope encrypted).
 *
 * Owns "where do we call". Never owns pricing — that stays in the Inference
 * domain (`ai_inference_providers`, `ai_provider_model_rates`).
 */
class AiConnection extends Model
{
    use HasUuids;

    public const ENCRYPTION_RESOURCE_TYPE = 'ai_connections';

    public const ENCRYPTION_FIELD_SECRET = 'secret_payload';

    public const ENCRYPTION_PURPOSE = 'ai';

    protected $table = 'ai_connections';

    /**
     * Never mass-assign the encrypted payload from request bags.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'label',
        'driver',
        'endpoint',
        'secret_fingerprint',
        'secret_hint',
        'test_model',
        'timeout_seconds',
        'connect_timeout_seconds',
        'max_retries',
        'extra_headers',
        'is_default',
        'status',
        'verified_at',
        'last_used_at',
        'last_error_at',
        'last_error_message',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret_payload',
        'secret_fingerprint',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => AiConnectionDriver::class,
            'status' => AiConnectionStatus::class,
            'secret_payload' => EnvelopeEncryptedArray::class.':'
                .self::ENCRYPTION_RESOURCE_TYPE.','
                .self::ENCRYPTION_FIELD_SECRET.','
                .self::ENCRYPTION_PURPOSE,
            'extra_headers' => 'array',
            'is_default' => 'boolean',
            'timeout_seconds' => 'integer',
            'connect_timeout_seconds' => 'integer',
            'max_retries' => 'integer',
            'verified_at' => 'datetime',
            'last_used_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    /**
     * Context for secret_payload decrypt cache (must match the cast).
     */
    public function decryptionContext(): DecryptionContext
    {
        return new DecryptionContext(
            resourceType: self::ENCRYPTION_RESOURCE_TYPE,
            resourceId: is_scalar($this->getKey()) ? (string) $this->getKey() : null,
            field: self::ENCRYPTION_FIELD_SECRET,
            purpose: self::ENCRYPTION_PURPOSE,
        );
    }

    public function isActive(): bool
    {
        return $this->status === AiConnectionStatus::Active;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
