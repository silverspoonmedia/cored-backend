<?php

namespace App\Domain\Vtual\Support;

use App\Domain\ErrorLog\Support\ErrorContextRedactor;
use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Enums\IncidentStatus;
use App\Domain\Vtual\Models\VtualIncident;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records vTual integration incidents. **Never throws to callers.**
 *
 * This is the write side of the incident table and the only place that decides
 * grouping and reopen semantics. Call it from an existing failure branch, not
 * from a `catch` that already logs: the incident is the *triage* record, and the
 * caller keeps its own `last_error` / `ErrorLog` behaviour.
 *
 * Grouping: `VtualIncidentFingerprint` collapses repeats into one row and bumps
 * `occurrences`. A repeat of an `acknowledged` / `resolved` row reopens it — a
 * resolved incident that fires again is not resolved, and leaving it so hides
 * the regression. `resolve()` is the reverse: a recovery path closes its own
 * incident, and a later recurrence simply reopens it.
 *
 * Octane-safe: no static request state; the recursion guard is instance-local
 * and always cleared in `finally` (the container may hold this for the worker's
 * lifetime).
 */
class VtualIncidentRecorder
{
    private bool $recording = false;

    public function __construct(
        private readonly VtualIncidentFingerprint $fingerprint,
        private readonly ErrorContextRedactor $redactor,
        private readonly VtualIncidentRealtime $realtime,
    ) {}

    /**
     * Record one occurrence of an integration failure.
     *
     * @param  array<string, mixed>  $context  Redacted before write.
     * @return VtualIncident|null Null when disabled, already recording, or the write failed.
     */
    public function record(
        IncidentKind $kind,
        IncidentSeverity $severity,
        string $title,
        ?string $detail = null,
        array $context = [],
        ?string $platformId = null,
        ?string $channelId = null,
        ?string $credentialId = null,
    ): ?VtualIncident {
        if (! $this->enabled() || $this->recording) {
            return null;
        }

        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $this->recording = true;

        try {
            $title = $this->truncate($title, 255);
            $detail = $detail !== null ? $this->truncate($detail, $this->maxDetailLength()) : null;

            $fingerprint = $this->fingerprint->make(
                $kind->value,
                $platformId,
                $channelId,
                $credentialId,
                $title,
                $detail,
            );

            $incident = $this->upsertOccurrence($fingerprint, [
                'kind' => $kind,
                'severity' => $severity,
                'platform_id' => $platformId,
                'channel_id' => $channelId,
                'credential_id' => $credentialId,
                'title' => $title,
                'detail' => $detail,
                'context' => $this->limitContext($this->redactor->redact($context)),
            ]);

            $this->realtime->changed(
                'recorded',
                (string) $incident->id,
                $incident->kind,
                $incident->severity,
                $incident->status,
            );

            return $incident;
        } catch (Throwable $e) {
            // Never break the ingestion path that was already failing.
            $this->logFailure($e);

            return null;
        } finally {
            $this->recording = false;
        }
    }

    /**
     * Close the incident a recovery path just fixed, if one is open.
     *
     * Matched on **kind + subject + title**, not on the fingerprint: a recovery
     * path knows *what* it fixed, not the volatile `detail` the failing call
     * happened to record (a 503 vs a 500 is the same incident, and the
     * fingerprint already normalizes those away, but the caller should not have
     * to reproduce the string to close it). Title is the stable human label
     * shared by both sides.
     *
     * Only `open` / `acknowledged` rows are touched: a resolved row is already
     * where the caller wants it, and rewriting its timestamps would erase when
     * the fix actually landed. A later recurrence reopens it anyway.
     */
    public function resolve(
        IncidentKind $kind,
        string $title,
        ?string $platformId = null,
        ?string $channelId = null,
        ?string $credentialId = null,
        ?string $resolvedBy = null,
    ): ?VtualIncident {
        if (! $this->enabled() || $this->recording) {
            return null;
        }

        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $this->recording = true;

        try {
            $incident = VtualIncident::query()
                ->where('kind', $kind->value)
                ->where('title', $this->truncate($title, 255))
                ->where('platform_id', $platformId)
                ->where('channel_id', $channelId)
                ->where('credential_id', $credentialId)
                ->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::Acknowledged->value])
                ->latest('last_seen_at')
                ->first();

            if ($incident === null) {
                return null;
            }

            $incident->fill([
                'status' => IncidentStatus::Resolved,
                'resolved_at' => Carbon::now(),
                'resolved_by' => $resolvedBy,
            ])->save();

            $this->realtime->changed(
                'resolved',
                (string) $incident->id,
                $incident->kind,
                $incident->severity,
                $incident->status,
            );

            return $incident->refresh();
        } catch (Throwable $e) {
            $this->logFailure($e);

            return null;
        } finally {
            $this->recording = false;
        }
    }

    /**
     * @param  array{
     *     kind: IncidentKind,
     *     severity: IncidentSeverity,
     *     platform_id: ?string,
     *     channel_id: ?string,
     *     credential_id: ?string,
     *     title: string,
     *     detail: ?string,
     *     context: array<string, mixed>
     * }  $attributes
     */
    private function upsertOccurrence(string $fingerprint, array $attributes): VtualIncident
    {
        $now = Carbon::now();

        try {
            $existing = VtualIncident::query()->where('fingerprint', $fingerprint)->first();

            if ($existing === null) {
                return VtualIncident::query()->create([
                    'fingerprint' => $fingerprint,
                    'kind' => $attributes['kind'],
                    'severity' => $attributes['severity'],
                    'status' => IncidentStatus::Open,
                    'platform_id' => $attributes['platform_id'],
                    'channel_id' => $attributes['channel_id'],
                    'credential_id' => $attributes['credential_id'],
                    'title' => $attributes['title'],
                    'detail' => $attributes['detail'],
                    'context' => $attributes['context'],
                    'occurrences' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }
        } catch (QueryException $e) {
            // Race on the unique fingerprint: fall through to the update path.
            $existing = VtualIncident::query()->where('fingerprint', $fingerprint)->first();

            if ($existing === null) {
                throw $e;
            }
        }

        $existing->fill([
            'severity' => $attributes['severity'],
            'detail' => $attributes['detail'],
            'context' => $attributes['context'],
            'last_seen_at' => $now,
            'occurrences' => $existing->occurrences + 1,
        ]);

        // A recurrence of a closed incident reopens it and clears the triage
        // stamps — leaving them would claim a fix that did not hold.
        if ($existing->status !== IncidentStatus::Open) {
            $existing->status = IncidentStatus::Open;
            $existing->acknowledged_at = null;
            $existing->acknowledged_by = null;
            $existing->resolved_at = null;
            $existing->resolved_by = null;
        }

        $existing->save();

        return $existing->refresh();
    }

    private function enabled(): bool
    {
        return (bool) setting_config('vtual.incidents.enabled', true);
    }

    private function maxDetailLength(): int
    {
        return max(0, (int) config('vtual.incidents.max_detail_length', 4000));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function limitContext(array $context): array
    {
        $max = max(0, (int) config('vtual.incidents.max_context_json_length', 16384));
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false || strlen($json) <= $max) {
            return $context;
        }

        return [
            '_truncated' => true,
            'preview' => $this->truncate($json, $max),
        ];
    }

    private function truncate(string $value, int $max): string
    {
        if ($max <= 0 || strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max).'…';
    }

    /**
     * A recorder that cannot record is worth knowing about — but the report must
     * not go through the same failing path, so it goes to the framework log.
     */
    private function logFailure(Throwable $e): void
    {
        try {
            Log::warning('vtual.incident_record_failed', [
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        } catch (Throwable) {
            // Nothing left to do.
        }
    }
}
