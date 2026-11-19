<?php

namespace App\Domain\Ai\Http\Resources;

use App\Domain\Ai\Models\AiConnection;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mask-only representation — never exposes secret_payload or fingerprint.
 *
 * @mixin AiConnection
 */
class AiConnectionResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AiConnection $connection */
        $connection = $this->resource;

        return [
            'id' => $connection->id,
            'slug' => $connection->slug,
            'label' => $connection->label,
            'driver' => $connection->driver->value,
            'endpoint' => $connection->endpoint,
            'test_model' => $connection->test_model,
            'timeout_seconds' => $connection->timeout_seconds,
            'connect_timeout_seconds' => $connection->connect_timeout_seconds,
            'max_retries' => $connection->max_retries,
            'has_extra_headers' => is_array($connection->extra_headers) && $connection->extra_headers !== [],
            'is_default' => (bool) $connection->is_default,
            'status' => $connection->status->value,
            'secret_hint' => $connection->secret_hint,
            'has_secret' => $connection->secret_hint !== null && $connection->secret_hint !== '',
            ...$this->dateFields('verified_at', $connection->verified_at),
            ...$this->dateFields('last_used_at', $connection->last_used_at),
            ...$this->dateFields('last_error_at', $connection->last_error_at),
            'last_error_message' => $connection->last_error_message,
            'created_by' => $connection->created_by,
            ...$this->dateFields('created_at', $connection->created_at),
            ...$this->dateFields('updated_at', $connection->updated_at),
        ];
    }
}
