<?php

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Contracts\AiConnectionProbe;
use App\Domain\Ai\Enums\AiConnectionDriver;
use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Exceptions\AiException;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Support\AiConnectionRealtime;
use App\Domain\Ai\Support\AiConnectionSecrets;
use App\Domain\Ai\Support\AiConnectionTestResult;
use App\Domain\Identity\Models\User;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lifecycle for AI connections.
 *
 * Test gate: nothing is resolvable until a
 * manual Test succeeds. Any edit that touches the secret or the endpoint
 * demotes the row back to `pending`.
 */
class AiConnectionService
{
    public function __construct(
        private readonly EnvelopeEncryptionService $encryption,
        private readonly AiConnectionProbe $probe,
        private readonly AiConnectionResolver $resolver,
        private readonly AiConnectionRealtime $realtime,
    ) {}

    /**
     * @return Collection<int, AiConnection>
     */
    public function list(): Collection
    {
        return AiConnection::query()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }

    public function findOrFail(string $id): AiConnection
    {
        return AiConnection::query()->whereKey($id)->firstOrFail();
    }

    /**
     * @param  array{
     *     slug: string,
     *     label: string,
     *     endpoint: string,
     *     test_model: string,
     *     driver?: string|null,
     *     secret: array<string, mixed>,
     *     timeout_seconds?: int|null,
     *     connect_timeout_seconds?: int|null,
     *     max_retries?: int|null,
     *     extra_headers?: array<string, mixed>|null,
     *     is_default?: bool|null,
     * }  $data
     */
    public function create(User $actor, array $data): AiConnection
    {
        $slug = $this->normalizeSlug($data['slug']);
        $payload = AiConnectionSecrets::normalizePayload($data['secret']);

        $connection = DB::transaction(function () use ($actor, $data, $slug, $payload): AiConnection {
            $this->assertSlugUnique($slug);

            $connection = new AiConnection([
                'slug' => $slug,
                'label' => $this->normalizeLabel($data['label']),
                'driver' => AiConnectionDriver::from($data['driver'] ?? AiConnectionDriver::OpenAiCompatible->value),
                'endpoint' => $this->normalizeEndpoint($data['endpoint']),
                'secret_fingerprint' => AiConnectionSecrets::fingerprint($payload),
                'secret_hint' => AiConnectionSecrets::hint($payload),
                'test_model' => $this->normalizeTestModel($data['test_model']),
                'timeout_seconds' => $this->nullableInt($data['timeout_seconds'] ?? null),
                'connect_timeout_seconds' => $this->nullableInt($data['connect_timeout_seconds'] ?? null),
                'max_retries' => $this->nullableInt($data['max_retries'] ?? null),
                'extra_headers' => $this->normalizeHeaders($data['extra_headers'] ?? null),
                'is_default' => false,
                // Never active on create — a manual Test promotes it.
                'status' => AiConnectionStatus::Pending,
                'verified_at' => null,
                'created_by' => $actor->id,
            ]);

            $connection->id = (string) Str::uuid7();
            $connection->secret_payload = $payload;
            $connection->save();

            if (($data['is_default'] ?? false) === true) {
                $connection = $this->promoteToDefault($connection);
            }

            return $connection;
        });

        $this->realtime->changed('created', (string) $connection->id, (string) $connection->slug, $connection->status?->value);

        return $connection;
    }

    /**
     * @param  array{
     *     slug?: string|null,
     *     label?: string|null,
     *     endpoint?: string|null,
     *     test_model?: string|null,
     *     driver?: string|null,
     *     secret?: array<string, mixed>|null,
     *     timeout_seconds?: int|null,
     *     connect_timeout_seconds?: int|null,
     *     max_retries?: int|null,
     *     extra_headers?: array<string, mixed>|null,
     *     is_default?: bool|null,
     *     status?: string|null,
     * }  $data
     */
    public function update(User $actor, AiConnection $connection, array $data): AiConnection
    {
        $updated = DB::transaction(function () use ($actor, $connection, $data): AiConnection {
            /** @var AiConnection $locked */
            $locked = AiConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();

            $identityChanged = false;

            if (array_key_exists('slug', $data) && is_string($data['slug']) && trim($data['slug']) !== '') {
                $slug = $this->normalizeSlug($data['slug']);
                if ($slug !== $locked->slug) {
                    $this->assertSlugUnique($slug, (string) $locked->id);
                    $this->resolver->forget((string) $locked->slug);
                    $locked->slug = $slug;
                }
            }

            if (array_key_exists('label', $data) && is_string($data['label']) && trim($data['label']) !== '') {
                $locked->label = $this->normalizeLabel($data['label']);
            }

            if (array_key_exists('driver', $data) && is_string($data['driver']) && $data['driver'] !== '') {
                $newDriver = AiConnectionDriver::from($data['driver']);
                if ($newDriver !== $locked->driver) {
                    $locked->driver = $newDriver;
                    $identityChanged = true;
                }
            }

            if (array_key_exists('endpoint', $data) && is_string($data['endpoint']) && trim($data['endpoint']) !== '') {
                $endpoint = $this->normalizeEndpoint($data['endpoint']);
                if ($endpoint !== $locked->endpoint) {
                    $locked->endpoint = $endpoint;
                    $identityChanged = true;
                }
            }

            if (array_key_exists('test_model', $data) && is_string($data['test_model']) && trim($data['test_model']) !== '') {
                $locked->test_model = $this->normalizeTestModel($data['test_model']);
            }

            if (array_key_exists('timeout_seconds', $data)) {
                $locked->timeout_seconds = $this->nullableInt($data['timeout_seconds']);
            }

            if (array_key_exists('connect_timeout_seconds', $data)) {
                $locked->connect_timeout_seconds = $this->nullableInt($data['connect_timeout_seconds']);
            }

            if (array_key_exists('max_retries', $data)) {
                $locked->max_retries = $this->nullableInt($data['max_retries']);
            }

            if (array_key_exists('extra_headers', $data)) {
                $locked->extra_headers = $this->normalizeHeaders($data['extra_headers']);
            }

            if (array_key_exists('secret', $data) && is_array($data['secret']) && $data['secret'] !== []) {
                $payload = AiConnectionSecrets::normalizePayload($data['secret']);
                $fingerprint = AiConnectionSecrets::fingerprint($payload);
                if ($fingerprint !== $locked->secret_fingerprint) {
                    $locked->secret_payload = $payload;
                    $locked->secret_fingerprint = $fingerprint;
                    $locked->secret_hint = AiConnectionSecrets::hint($payload);
                    $identityChanged = true;
                }
            }

            if (array_key_exists('status', $data) && is_string($data['status']) && $data['status'] !== '') {
                $status = AiConnectionStatus::from($data['status']);
                if ($status !== $locked->status) {
                    $locked->status = $status;
                    if ($status !== AiConnectionStatus::Active) {
                        $locked->verified_at = null;
                    }
                }
            }

            // Anything that changes how we authenticate or where we call
            // invalidates the previous Test result.
            if ($identityChanged) {
                $locked->status = AiConnectionStatus::Pending;
                $locked->verified_at = null;
            }

            $locked->save();

            if (($data['is_default'] ?? null) === true) {
                $locked = $this->promoteToDefault($locked);
            }

            return $locked;
        });

        $this->invalidateDecryptCache($updated);
        $this->resolver->forget((string) $updated->slug);

        $this->realtime->changed('updated', (string) $updated->id, (string) $updated->slug, $updated->status?->value);

        return $updated;
    }

    /**
     * Promote to the single default connection (demotes every other row).
     */
    public function setDefault(AiConnection $connection): AiConnection
    {
        $updated = DB::transaction(function () use ($connection): AiConnection {
            /** @var AiConnection $locked */
            $locked = AiConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AiConnectionStatus::Active) {
                throw new AiException(__('api/ai.connection.default_requires_active'), 422);
            }

            return $this->promoteToDefault($locked);
        });

        $this->realtime->changed('default', (string) $updated->id, (string) $updated->slug, $updated->status?->value);

        return $updated;
    }

    public function delete(AiConnection $connection): void
    {
        if ($connection->is_default) {
            throw new AiException(__('api/ai.connection.delete_requires_default_handover'), 422);
        }

        $slug = (string) $connection->slug;

        $this->invalidateDecryptCache($connection);
        $connection->delete();
        $this->resolver->forget($slug);

        $this->realtime->changed('deleted', null, $slug, null);
    }

    /**
     * Manual Test gate: probe the endpoint, then activate on success.
     */
    public function test(AiConnection $connection): AiConnectionTestResult
    {
        $payload = $connection->secret_payload;

        if (! is_array($payload) || $payload === []) {
            throw new AiException(__('api/ai.connection.payload_invalid'), 422);
        }

        $result = $this->probe->probe($connection, $payload);

        if (! $result->ok) {
            $this->markFailed($connection, $result->message);

            return new AiConnectionTestResult(
                ok: false,
                message: $result->message,
                latencyMs: $result->latencyMs,
                connection: $connection->fresh() ?? $connection,
            );
        }

        $updated = DB::transaction(function () use ($connection): AiConnection {
            /** @var AiConnection $locked */
            $locked = AiConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();

            $locked->status = AiConnectionStatus::Active;
            $locked->verified_at = now();
            $locked->last_used_at = now();
            $locked->last_error_at = null;
            $locked->last_error_message = null;
            $locked->save();

            return $locked;
        });

        $this->resolver->forget((string) $updated->slug);

        $this->realtime->changed('tested', (string) $updated->id, (string) $updated->slug, $updated->status?->value);

        return new AiConnectionTestResult(
            ok: true,
            message: __('api/ai.connection.test_passed'),
            latencyMs: $result->latencyMs,
            connection: $updated,
        );
    }

    /**
     * Healthy use: stamp last_used_at. Never invalidates the decrypt cache.
     */
    public function markUsed(AiConnection $connection): void
    {
        $connection->forceFill(['last_used_at' => now()])->save();
    }

    /**
     * Probe/HTTP failure: stamp last_error_* so the admin UI shows it.
     */
    public function markFailed(AiConnection $connection, string $message): void
    {
        $trimmed = trim($message);
        if (mb_strlen($trimmed) > 255) {
            $trimmed = mb_substr($trimmed, 0, 255);
        }

        $connection->forceFill([
            'last_error_at' => now(),
            'last_error_message' => $trimmed !== '' ? $trimmed : null,
        ])->save();

        $this->invalidateDecryptCache($connection);

        $this->realtime->changed('test_failed', (string) $connection->id, (string) $connection->slug, $connection->status?->value);
    }

    /**
     * Drop the cached plaintext key for this row.
     */
    public function invalidateDecryptCache(AiConnection $connection): void
    {
        $this->encryption->decryptedValueCacheForget($connection->decryptionContext());
    }

    private function promoteToDefault(AiConnection $connection): AiConnection
    {
        AiConnection::query()
            ->whereKeyNot($connection->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $connection->forceFill(['is_default' => true])->save();

        $this->resolver->forget();

        return $connection;
    }

    private function assertSlugUnique(string $slug, ?string $ignoreId = null): void
    {
        $query = AiConnection::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new AiException(__('api/ai.connection.slug_taken'), 422);
        }
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = (string) preg_replace('/[^a-z0-9._-]+/', '-', $normalized);
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            throw new AiException(__('api/ai.connection.slug_required'), 422);
        }

        return $normalized;
    }

    private function normalizeLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            throw new AiException(__('api/ai.connection.label_required'), 422);
        }

        return $normalized;
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        $normalized = rtrim(trim($endpoint), '/');
        if ($normalized === '' || ! str_starts_with($normalized, 'http')) {
            throw new AiException(__('api/ai.connection.endpoint_invalid'), 422);
        }

        return $normalized;
    }

    private function normalizeTestModel(string $model): string
    {
        $normalized = trim($model);
        if ($normalized === '') {
            throw new AiException(__('api/ai.connection.test_model_required'), 422);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $headers
     * @return array<string, string>|null
     */
    private function normalizeHeaders(?array $headers): ?array
    {
        if ($headers === null || $headers === []) {
            return null;
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            if (! is_string($name) || trim($name) === '' || ! is_scalar($value)) {
                continue;
            }

            $normalized[trim($name)] = trim((string) $value);
        }

        return $normalized === [] ? null : $normalized;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}
