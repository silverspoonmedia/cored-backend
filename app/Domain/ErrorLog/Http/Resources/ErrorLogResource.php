<?php

namespace App\Domain\ErrorLog\Http\Resources;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ErrorLog
 */
class ErrorLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fingerprint' => $this->fingerprint,
            'type' => $this->type,
            'level' => $this->level,
            'exception_class' => $this->exception_class,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'context' => $this->context ?? [],
            'occurrence_count' => $this->occurrence_count,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'status' => $this->status,
            'status_label' => $this->resolveStatusLabel(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->resolveResolver(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveStatusLabel(): string
    {
        $key = 'api/error-log.statuses.'.$this->status;

        return __($key) !== $key ? __($key) : (string) $this->status;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveResolver(): ?array
    {
        $resolver = $this->resolver;

        if (! $resolver instanceof User) {
            return null;
        }

        return [
            'id' => $resolver->id,
            'name' => $resolver->name,
            'email' => $resolver->email,
        ];
    }
}
