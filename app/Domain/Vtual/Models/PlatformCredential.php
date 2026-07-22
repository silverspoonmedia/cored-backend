<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Support\Encryption\Casts\EnvelopeEncryptedArray;
use App\Support\Encryption\DecryptionContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PlatformCredential extends Model
{
    use HasUuids;

    public const ENCRYPTION_RESOURCE_TYPE = 'vtual_platform_credentials';

    public const ENCRYPTION_FIELD_SECRET = 'secret_payload';

    public const ENCRYPTION_FIELD_RUNTIME = 'runtime_payload';

    public const ENCRYPTION_PURPOSE = 'vtual';

    protected $table = 'vtual_platform_credentials';

    /**
     * Never mass-assign encrypted payloads accidentally from request bags.
     *
     * @var list<string>
     */
    protected $fillable = [
        'platform_id',
        'label',
        'type',
        'secret_fingerprint',
        'secret_hint',
        'priority',
        'status',
        'last_used_at',
        'last_error_at',
        'last_error_message',
        'consecutive_auth_failures',
        'failover_skip_until',
        'verified_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret_payload',
        'secret_fingerprint',
        'runtime_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlatformCredentialType::class,
            'status' => PlatformCredentialStatus::class,
            'secret_payload' => EnvelopeEncryptedArray::class.':'
                .self::ENCRYPTION_RESOURCE_TYPE.','
                .self::ENCRYPTION_FIELD_SECRET.','
                .self::ENCRYPTION_PURPOSE,
            'runtime_payload' => EnvelopeEncryptedArray::class.':'
                .self::ENCRYPTION_RESOURCE_TYPE.','
                .self::ENCRYPTION_FIELD_RUNTIME.','
                .self::ENCRYPTION_PURPOSE,
            'priority' => 'integer',
            'last_used_at' => 'datetime',
            'last_error_at' => 'datetime',
            'consecutive_auth_failures' => 'integer',
            'failover_skip_until' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Context for secret_payload decrypt cache (must match cast).
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

    /**
     * Context for runtime_payload decrypt cache (must match cast).
     */
    public function runtimeDecryptionContext(): DecryptionContext
    {
        return new DecryptionContext(
            resourceType: self::ENCRYPTION_RESOURCE_TYPE,
            resourceId: is_scalar($this->getKey()) ? (string) $this->getKey() : null,
            field: self::ENCRYPTION_FIELD_RUNTIME,
            purpose: self::ENCRYPTION_PURPOSE,
        );
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === PlatformCredentialStatus::Active;
    }

    public function hasRuntimePayload(): bool
    {
        $runtime = $this->runtime_payload;

        return is_array($runtime) && $runtime !== [];
    }

    public function runtimeExpiresAt(): ?Carbon
    {
        $runtime = $this->runtime_payload;
        if (! is_array($runtime)) {
            return null;
        }

        $expiresAt = $runtime['expires_at'] ?? null;
        if (! is_string($expiresAt) || $expiresAt === '') {
            return null;
        }

        try {
            return Carbon::parse($expiresAt);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Decrypt admin secret for internal workers only. Prefer service resolvePayload().
     *
     * @return array<string, mixed>|null
     */
    public function decryptedPayload(): ?array
    {
        return $this->secret_payload;
    }
}
