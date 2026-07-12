<?php

namespace App\Domain\Playground\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Playground\Support\Orders\OrderAttachmentStorage;
use App\Support\Encryption\Casts\EnvelopeEncrypted;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A single stored file's metadata, owned polymorphically by any model
 * (an {@see Order} today, anything tomorrow). The binary lives on a storage
 * `disk`; this row only keeps the lightweight descriptor — `path` is the
 * disk-relative key used to build a URL or delete the object — plus the
 * `user_id` of whoever owns/uploaded it.
 *
 * Files are written/removed by {@see OrderAttachmentStorage}
 * (pure file I/O); these rows are persisted inside the owner's DB transaction so
 * a row never exists without a committed parent.
 *
 * URL resolution lives here because it is disk-aware: a public disk returns a
 * plain, non-expiring URL; an S3-compatible disk mints a **signed** (temporary)
 * URL, which is expensive and itself expires — so it is cached on the row
 * (`cached_url` + `cached_url_expires_at`) and regenerated only when the current
 * time nears expiry.
 */
#[Fillable([
    'user_id',
    'disk',
    'path',
    'name',
    'size',
    'mime',
    'cached_url',
    'cached_url_expires_at',
])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): AttachmentFactory
    {
        return AttachmentFactory::new();
    }

    /**
     * How long a freshly minted signed URL stays valid.
     */
    public const SIGNED_URL_TTL_MINUTES = 60;

    /**
     * Refresh the cached signed URL once it is within this window of expiring,
     * so a caller never receives an about-to-die URL.
     */
    public const SIGNED_URL_REFRESH_THRESHOLD_MINUTES = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'cached_url' => EnvelopeEncrypted::class.':attachments,cached_url,signed_url',
            'cached_url_expires_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who owns/uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Public/temporary URL for the file.
     *
     * Public disks return a plain, non-expiring URL. S3-compatible disks return
     * a signed URL cached on the row: a cache hit reuses `cached_url` until it
     * nears `cached_url_expires_at`; otherwise a fresh signed URL is minted,
     * persisted, and returned. Persisting is best-effort — the URL is always
     * returned even if the row cannot be saved (e.g. an unsaved model).
     */
    public function url(): string
    {
        $disk = Storage::disk($this->disk);

        // Plain, non-expiring URL for disks that do not sign (public/local).
        if (! $disk->providesTemporaryUrls()) {
            return $disk->url($this->path);
        }

        if ($this->hasFreshCachedUrl()) {
            return $this->cached_url;
        }

        $expiresAt = Carbon::now()->addMinutes(self::SIGNED_URL_TTL_MINUTES);
        $url = $disk->temporaryUrl($this->path, $expiresAt);

        $this->forceFill([
            'cached_url' => $url,
            'cached_url_expires_at' => $expiresAt,
        ]);

        // Best-effort cache write; never let a persistence hiccup block the URL.
        if ($this->exists) {
            $this->save();
        }

        return $url;
    }

    /**
     * True when a cached signed URL exists and is not within the refresh window
     * of expiring.
     */
    private function hasFreshCachedUrl(): bool
    {
        return $this->cached_url !== null
            && $this->cached_url_expires_at !== null
            && $this->cached_url_expires_at->isAfter(
                Carbon::now()->addMinutes(self::SIGNED_URL_REFRESH_THRESHOLD_MINUTES),
            );
    }
}
