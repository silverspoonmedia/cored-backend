<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Models\PlatformCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mask-only representation — never exposes secret_payload or fingerprint.
 *
 * @mixin PlatformCredential
 */
class PlatformCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PlatformCredential $credential */
        $credential = $this->resource;

        $platform = $credential->relationLoaded('platform') ? $credential->platform : null;

        return [
            'id' => $credential->id,
            'platform_id' => $credential->platform_id,
            'platform_key' => $platform?->key?->value ?? $platform?->key,
            'platform_name' => $platform?->name,
            'label' => $credential->label,
            'type' => $credential->type?->value ?? $credential->type,
            'status' => $credential->status?->value ?? $credential->status,
            'priority' => $credential->priority,
            'secret_hint' => $credential->secret_hint,
            'has_secret' => $credential->secret_hint !== null && $credential->secret_hint !== '',
            'has_runtime' => $credential->hasRuntimePayload(),
            'runtime_expires_at' => $credential->runtimeExpiresAt()?->toIso8601String(),
            'verified_at' => $credential->verified_at?->toIso8601String(),
            'last_used_at' => $credential->last_used_at?->toIso8601String(),
            'last_error_at' => $credential->last_error_at?->toIso8601String(),
            'last_error_message' => $credential->last_error_message,
            'created_by' => $credential->created_by,
            'created_at' => $credential->created_at?->toIso8601String(),
            'updated_at' => $credential->updated_at?->toIso8601String(),
        ];
    }
}
