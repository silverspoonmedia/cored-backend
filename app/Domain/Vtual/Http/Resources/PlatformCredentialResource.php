<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Models\PlatformCredential;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mask-only representation — never exposes secret_payload or fingerprint.
 *
 * @mixin PlatformCredential
 */
class PlatformCredentialResource extends JsonResource
{
    use FormatsPresentation;

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
            ...$this->dateFields('runtime_expires_at', $credential->runtimeExpiresAt()),
            ...$this->dateFields('verified_at', $credential->verified_at),
            ...$this->dateFields('last_used_at', $credential->last_used_at),
            ...$this->dateFields('last_error_at', $credential->last_error_at),
            'last_error_message' => $credential->last_error_message,
            'consecutive_auth_failures' => (int) ($credential->consecutive_auth_failures ?? 0),
            ...$this->dateFields('failover_skip_until', $credential->failover_skip_until),
            'created_by' => $credential->created_by,
            ...$this->dateFields('created_at', $credential->created_at),
            ...$this->dateFields('updated_at', $credential->updated_at),
        ];
    }
}
