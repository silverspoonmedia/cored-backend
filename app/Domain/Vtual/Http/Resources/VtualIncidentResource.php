<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Models\VtualIncident;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops-facing incident row.
 *
 * `context` is already redacted at write time (`ErrorContextRedactor` inside
 * `VtualIncidentRecorder`), so it is safe to expose to the staff permission that
 * gates this endpoint — but it is the only place secrets could ever have leaked,
 * which is why the recorder redacts on the way in rather than here on the way
 * out. A resource-level filter would leave the raw value in the database for the
 * next consumer to forget about.
 *
 * @mixin VtualIncident
 */
class VtualIncidentResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var VtualIncident $incident */
        $incident = $this->resource;

        return [
            'id' => $incident->id,
            'kind' => $incident->kind->value,
            'kind_label' => $this->translate('api/vtual.incident.kinds.'.$incident->kind->value),
            'severity' => $incident->severity->value,
            'severity_label' => $this->translate('api/vtual.incident.severities.'.$incident->severity->value),
            'status' => $incident->status->value,
            'status_label' => $this->translate('api/vtual.incident.statuses.'.$incident->status->value),
            'platform_id' => $incident->platform_id,
            'platform_key' => $incident->relationLoaded('platform') && $incident->platform !== null
                ? ($incident->platform->key?->value ?? $incident->platform->key)
                : null,
            'platform_name' => $incident->relationLoaded('platform') ? $incident->platform?->name : null,
            'channel_id' => $incident->channel_id,
            'channel_handle' => $incident->relationLoaded('channel') ? $incident->channel?->handle : null,
            'channel_display_name' => $incident->relationLoaded('channel') ? $incident->channel?->display_name : null,
            'credential_id' => $incident->credential_id,
            'credential_label' => $incident->relationLoaded('credential') ? $incident->credential?->label : null,
            'title' => $incident->title,
            'detail' => $incident->detail,
            'context' => $incident->context ?? [],
            'occurrences' => $incident->occurrences,
            ...$this->dateFields('first_seen_at', $incident->first_seen_at),
            ...$this->dateFields('last_seen_at', $incident->last_seen_at),
            ...$this->dateFields('acknowledged_at', $incident->acknowledged_at),
            'acknowledged_by' => $this->actor($incident->acknowledger),
            ...$this->dateFields('resolved_at', $incident->resolved_at),
            'resolved_by' => $this->actor($incident->resolver),
            ...$this->dateFields('created_at', $incident->created_at),
            ...$this->dateFields('updated_at', $incident->updated_at),
        ];
    }

    /**
     * Enum labels come from `api/vtual`; an unmapped value falls back to the raw
     * enum value rather than rendering a dotted key to the user.
     */
    private function translate(string $key): string
    {
        $translated = __($key);

        return $translated === $key ? (string) last(explode('.', $key)) : $translated;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actor(mixed $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
