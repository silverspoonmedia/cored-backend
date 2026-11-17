<?php

namespace App\Domain\ActivityLog\Support;

use App\Providers\Domain\ActivityLogServiceProvider;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Envelope-encrypts PII stored inside `activity_log.properties` (write path).
 *
 * The audit trail keeps forensic usefulness — every other property key stays
 * plaintext, so `properties->some_key` JSON queries and list filters keep
 * working. Decryption for display happens in
 * {@see SensitiveActivityPropertyPresenter} only.
 *
 * Registered once via `LogActivityAction::beforeLogging()` in
 * {@see ActivityLogServiceProvider}.
 */
class SensitiveActivityProperties
{
    /** Decryption context resource type for activity-trail PII. */
    public const RESOURCE_TYPE = 'activity_log';

    public const PURPOSE_WRITE = 'activity_log_write';

    public const PURPOSE_READ = 'activity_log_read';

    public const PURPOSE_MIGRATION = 'migration';

    public static function enabled(): bool
    {
        return (bool) config('activitylog.sensitive_properties.enabled', false);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = (array) config('activitylog.sensitive_properties.keys', []);

        return array_values(array_filter(
            $keys,
            static fn (mixed $key): bool => is_string($key) && $key !== '',
        ));
    }

    public static function propertyField(string $key): string
    {
        return 'properties.'.$key;
    }

    public static function context(string $key, ?string $activityId, string $purpose): DecryptionContext
    {
        return new DecryptionContext(
            resourceType: self::RESOURCE_TYPE,
            resourceId: $activityId,
            field: self::propertyField($key),
            purpose: $purpose,
        );
    }

    /**
     * Replaces configured `properties` values with envelope JSON in place.
     *
     * Idempotent: values already stored as an envelope are left untouched, and
     * non-PII keys are never modified. Runs before the row is persisted.
     */
    public static function encryptInPlace(Activity $activity): void
    {
        if (! self::enabled()) {
            return;
        }

        $properties = $activity->properties;

        if ($properties === null) {
            return;
        }

        $attributes = $properties instanceof Collection
            ? $properties->all()
            : (array) $properties;

        $changed = false;

        foreach (self::keys() as $key) {
            $value = $attributes[$key] ?? null;

            if (! is_string($value) || $value === '' || EncryptedEnvelope::isEnvelopeJson($value)) {
                continue;
            }

            try {
                $attributes[$key] = app(EnvelopeEncryptionService::class)->encrypt(
                    $value,
                    self::context($key, null, self::PURPOSE_WRITE),
                );
            } catch (Throwable $exception) {
                // Never let a KMS failure break the request that is being audited
                // (login, decrypt, ...). Drop the PII instead of writing it in
                // plaintext; the failure is reported for ops.
                report($exception);

                unset($attributes[$key]);
            }

            $changed = true;
        }

        if ($changed) {
            $activity->properties = collect($attributes);
        }
    }
}
