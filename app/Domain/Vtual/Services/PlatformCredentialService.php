<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Support\PlatformErrorLogger;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Services\Probes\TwitchOauthAppProbe;
use App\Domain\Vtual\Support\PlatformCredentialSecrets;
use App\Domain\Vtual\Support\PlatformCredentialTestResult;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformCredentialService
{
    public function __construct(
        private readonly EnvelopeEncryptionService $encryption,
        private readonly PlatformCredentialProbeRegistry $probes,
    ) {}

    /**
     * @return Collection<int, PlatformCredential>
     */
    public function listForPlatform(Platform $platform): Collection
    {
        return PlatformCredential::query()
            ->where('platform_id', $platform->id)
            ->with('platform')
            ->orderBy('priority')
            ->orderBy('label')
            ->get();
    }

    public function findOrFail(string $id): PlatformCredential
    {
        return PlatformCredential::query()
            ->with('platform')
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array{
     *     label: string,
     *     type: string,
     *     priority?: int|null,
     *     status?: string|null,
     *     secret: array<string, mixed>
     * }  $data
     */
    public function create(User $actor, Platform $platform, array $data): PlatformCredential
    {
        $type = PlatformCredentialType::from($data['type']);
        $payload = PlatformCredentialSecrets::normalizePayload($type, $data['secret']);
        $label = $this->normalizeLabel($data['label']);
        $priority = (int) ($data['priority'] ?? 100);
        // Always pending until manual Test succeeds (ignore client status).
        $status = PlatformCredentialStatus::Pending;

        return DB::transaction(function () use ($actor, $platform, $type, $payload, $label, $priority, $status): PlatformCredential {
            $this->assertLabelUnique($platform->id, $label);

            $credential = new PlatformCredential([
                'platform_id' => $platform->id,
                'label' => $label,
                'type' => $type,
                'secret_fingerprint' => PlatformCredentialSecrets::fingerprint($type, $payload),
                'secret_hint' => PlatformCredentialSecrets::hint($type, $payload),
                'priority' => max(0, $priority),
                'status' => $status,
                'verified_at' => null,
                'created_by' => $actor->id,
            ]);
            $credential->id = (string) Str::uuid7();
            $credential->secret_payload = $payload;
            $credential->runtime_payload = null;
            $credential->save();

            return $credential->load('platform');
        });
    }

    /**
     * @param  array{
     *     label?: string|null,
     *     priority?: int|null,
     *     status?: string|null,
     *     secret?: array<string, mixed>|null
     * }  $data
     */
    public function update(PlatformCredential $credential, array $data): PlatformCredential
    {
        return DB::transaction(function () use ($credential, $data): PlatformCredential {
            $locked = PlatformCredential::query()->whereKey($credential->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('label', $data) && $data['label'] !== null) {
                $label = $this->normalizeLabel((string) $data['label']);
                $this->assertLabelUnique($locked->platform_id, $label, $locked->id);
                $locked->label = $label;
            }

            if (array_key_exists('priority', $data) && $data['priority'] !== null) {
                $locked->priority = max(0, (int) $data['priority']);
            }

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $newStatus = PlatformCredentialStatus::from((string) $data['status']);
                // Admin cannot promote to active without Test — only disable/revoke/pending.
                if ($newStatus === PlatformCredentialStatus::Active && $locked->status !== PlatformCredentialStatus::Active) {
                    throw new VtualException(__('api/vtual.credential.status_active_requires_test'), 422);
                }
                $locked->status = $newStatus;
            }

            if (array_key_exists('secret', $data) && is_array($data['secret']) && $data['secret'] !== []) {
                $type = $locked->type;
                $payload = PlatformCredentialSecrets::normalizePayload($type, $data['secret']);
                $locked->secret_payload = $payload;
                $locked->secret_fingerprint = PlatformCredentialSecrets::fingerprint($type, $payload);
                $locked->secret_hint = PlatformCredentialSecrets::hint($type, $payload);
                // Secret rotate → must re-Test.
                $locked->status = PlatformCredentialStatus::Pending;
                $locked->runtime_payload = null;
                $locked->verified_at = null;
            }

            $locked->save();

            return $locked->load('platform');
        });
    }

    public function disable(PlatformCredential $credential): PlatformCredential
    {
        return $this->update($credential, [
            'status' => PlatformCredentialStatus::Disabled->value,
        ]);
    }

    public function delete(PlatformCredential $credential): void
    {
        $this->invalidateDecryptCache($credential);
        $credential->delete();
    }

    /**
     * First eligible active credential by priority ASC.
     * Pending never selected. Soft-demoted rows (failover_skip_until in future) skipped.
     *
     * @param  list<string>  $excludeIds
     */
    public function pickActive(Platform $platform, array $excludeIds = []): ?PlatformCredential
    {
        $query = PlatformCredential::query()
            ->where('platform_id', $platform->id)
            ->where('status', PlatformCredentialStatus::Active)
            ->where(function ($q): void {
                $q->whereNull('failover_skip_until')
                    ->orWhere('failover_skip_until', '<=', now());
            })
            ->orderBy('priority')
            ->orderBy('created_at');

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->first();
    }

    /**
     * Manual Test gate: probe platform, optionally write runtime_payload, set active on success.
     */
    public function test(PlatformCredential $credential): PlatformCredentialTestResult
    {
        $credential->loadMissing('platform');

        if (in_array($credential->status, [PlatformCredentialStatus::Disabled, PlatformCredentialStatus::Revoked], true)) {
            throw new VtualException(__('api/vtual.credential.test_not_allowed_status'), 422);
        }

        $secret = $this->resolvePayload($credential);
        $probe = $this->probes->for($credential);
        $result = $probe->probe($credential, $secret);

        if (! $result->ok) {
            $this->markFailed($credential, $result->message);

            return new PlatformCredentialTestResult(
                ok: false,
                message: $result->message,
                platformKey: $result->platformKey,
                runtime: null,
                credential: $credential->fresh(['platform']),
            );
        }

        $updated = DB::transaction(function () use ($credential, $result): PlatformCredential {
            $locked = PlatformCredential::query()->whereKey($credential->id)->lockForUpdate()->firstOrFail();
            $locked->status = PlatformCredentialStatus::Active;
            $locked->verified_at = now();
            $locked->last_error_at = null;
            $locked->last_error_message = null;
            $locked->consecutive_auth_failures = 0;
            $locked->failover_skip_until = null;
            $locked->last_used_at = now();

            if (is_array($result->runtime) && $result->runtime !== []) {
                $locked->runtime_payload = $result->runtime;
            }

            $locked->save();

            return $locked->load('platform');
        });

        return new PlatformCredentialTestResult(
            ok: true,
            message: $result->message,
            platformKey: $result->platformKey,
            runtime: null,
            credential: $updated,
        );
    }

    /**
     * Twitch workers: return app access token, re-minting into runtime_payload when needed.
     */
    public function getAppAccessToken(PlatformCredential $credential): string
    {
        $credential->loadMissing('platform');

        if ($credential->status !== PlatformCredentialStatus::Active) {
            throw new VtualException(__('api/vtual.credential.not_active'), 422);
        }

        if ($credential->type !== PlatformCredentialType::OauthApp) {
            throw new VtualException(__('api/vtual.credential.app_token_oauth_only'), 422);
        }

        if ($credential->platform?->key !== PlatformKey::Twitch) {
            throw new VtualException(__('api/vtual.credential.app_token_twitch_only'), 422);
        }

        $lock = Cache::lock('vtual.cred.token.'.$credential->id, 15);

        return $lock->block(10, function () use ($credential): string {
            $fresh = $credential->fresh(['platform']);
            if ($fresh === null) {
                throw new VtualException(__('api/vtual.credential.payload_invalid'), 422);
            }

            $token = $this->validRuntimeAccessToken($fresh);
            if ($token !== null) {
                return $token;
            }

            $secret = $this->resolvePayload($fresh);
            $probe = app(TwitchOauthAppProbe::class);
            $result = $probe->probe($fresh, $secret);

            if (! $result->ok || ! is_array($result->runtime) || empty($result->runtime['access_token'])) {
                $this->markFailed($fresh, $result->message);
                throw new VtualException($result->message, 422);
            }

            $fresh->runtime_payload = $result->runtime;
            $fresh->last_used_at = now();
            $fresh->save();

            return (string) $result->runtime['access_token'];
        });
    }

    /**
     * Decrypt secret payload for workers. Uses envelope decrypt cache.
     *
     * @return array<string, mixed>
     */
    public function resolvePayload(PlatformCredential $credential): array
    {
        $payload = $credential->secret_payload;

        if (! is_array($payload) || $payload === []) {
            throw new VtualException(__('api/vtual.credential.payload_invalid'), 422);
        }

        return $payload;
    }

    /**
     * Healthy use: stamp last_used_at and clear soft-failover state.
     * Does not invalidate decrypt cache.
     */
    public function markUsed(PlatformCredential $credential): void
    {
        $credential->forceFill([
            'last_used_at' => now(),
            'consecutive_auth_failures' => 0,
            'failover_skip_until' => null,
        ])->save();
    }

    /**
     * Clear soft-failover counters without stamping last_used_at.
     */
    public function clearAuthFailureState(PlatformCredential $credential): void
    {
        $credential->forceFill([
            'consecutive_auth_failures' => 0,
            'failover_skip_until' => null,
        ])->save();
    }

    /**
     * Auth-local failure (401/403): stamp last_error_*, increment consecutive failures,
     * soft-demote when failover enabled and threshold met. Does not change status.
     * PlatformHttp already wrote ErrorLog for the HTTP response.
     */
    public function recordAuthFailure(PlatformCredential $credential, string $message): void
    {
        $this->markFailed($credential, $message, logToErrorLog: false);

        $credential->refresh();
        $failures = (int) $credential->consecutive_auth_failures + 1;
        $enabled = (bool) setting_config('vtual.credential.failover.enabled', false);
        $threshold = max(1, (int) setting_config('vtual.credential.failover.auth_fail_threshold', 2));
        $skipMinutes = max(1, (int) setting_config('vtual.credential.failover.skip_minutes', 30));

        $attrs = ['consecutive_auth_failures' => $failures];
        $demoted = false;

        if ($enabled && $failures >= $threshold) {
            $attrs['failover_skip_until'] = now()->addMinutes($skipMinutes);
            $demoted = true;
        }

        $credential->forceFill($attrs)->save();

        if (! $demoted) {
            return;
        }

        $credential->loadMissing('platform');
        $platformKey = $credential->platform?->key;
        $platform = $platformKey instanceof PlatformKey
            ? $platformKey->value
            : (is_string($platformKey) ? $platformKey : null);

        PlatformErrorLogger::log('Platform credential soft-demoted for failover', [
            'component' => 'platform_credential',
            'event' => 'credential_failover_demoted',
            'credential_id' => (string) $credential->id,
            'platform' => $platform,
            'consecutive_auth_failures' => $failures,
            'skip_until' => $credential->failover_skip_until?->toIso8601String(),
        ]);
    }

    /**
     * Integration failure: stamp last_error_* and force next secret resolve to re-decrypt.
     *
     * @param  bool  $logToErrorLog  False when PlatformHttp already recorded the same HTTP failure.
     */
    public function markFailed(PlatformCredential $credential, string $message, bool $logToErrorLog = true): void
    {
        $trimmed = trim($message);
        if (mb_strlen($trimmed) > 255) {
            $trimmed = mb_substr($trimmed, 0, 255);
        }

        $credential->forceFill([
            'last_error_at' => now(),
            'last_error_message' => $trimmed !== '' ? $trimmed : null,
        ])->save();

        $this->invalidateDecryptCache($credential);

        if (! $logToErrorLog) {
            return;
        }

        $credential->loadMissing('platform');
        $platformKey = $credential->platform?->key;
        $platform = $platformKey instanceof PlatformKey
            ? $platformKey->value
            : (is_string($platformKey) ? $platformKey : null);

        PlatformErrorLogger::log($trimmed !== '' ? $trimmed : 'Platform credential failure', [
            'component' => 'platform_credential',
            'event' => 'credential_mark_failed',
            'credential_id' => (string) $credential->id,
            'platform' => $platform,
        ]);
    }

    /**
     * Drop plaintext decrypt caches for secret + runtime fields.
     */
    public function invalidateDecryptCache(PlatformCredential $credential): void
    {
        $this->encryption->decryptedValueCacheForget($credential->decryptionContext());
        $this->encryption->decryptedValueCacheForget($credential->runtimeDecryptionContext());
    }

    private function validRuntimeAccessToken(PlatformCredential $credential): ?string
    {
        $runtime = $credential->runtime_payload;
        if (! is_array($runtime)) {
            return null;
        }

        $token = $runtime['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            return null;
        }

        $expiresAt = $runtime['expires_at'] ?? null;
        if (! is_string($expiresAt) || $expiresAt === '') {
            return null;
        }

        try {
            $expires = Carbon::parse($expiresAt);
        } catch (\Throwable) {
            return null;
        }

        $skew = (int) setting_config('vtual.credential_test.twitch_token_skew_seconds', 300);
        if ($expires->lessThanOrEqualTo(now()->addSeconds(max(0, $skew)))) {
            return null;
        }

        return $token;
    }

    private function normalizeLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            throw new VtualException(__('api/vtual.credential.label_required'), 422);
        }

        return $normalized;
    }

    private function assertLabelUnique(string $platformId, string $label, ?string $ignoreId = null): void
    {
        $query = PlatformCredential::query()
            ->where('platform_id', $platformId)
            ->where('label', $label);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new VtualException(__('api/vtual.credential.label_taken'), 422);
        }
    }
}
