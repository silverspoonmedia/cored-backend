<?php

namespace App\Http\Resources\Notification;

use App\Models\ActivityNotification;
use App\Notifications\Activity\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = NotificationType::tryFrom($this->type);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'message' => $this->resolveMessage($type),
            'icon' => $type?->icon() ?? 'bi bi-bell',
            'action_route' => $this->action_route,
            'action_params' => $this->action_params ?? [],
            // `is_read` is set by the repository (withCount alias); default false.
            'is_read' => (bool) ($this->getAttribute('is_read') ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve the localized, interpolated message for the current locale.
     */
    private function resolveMessage(?NotificationType $type): string
    {
        if ($type === null) {
            return '';
        }

        $data = is_array($this->data) ? $this->data : [];

        // Only string/number replacements are valid for the translator.
        $replace = array_filter(
            $data,
            fn ($value) => is_string($value) || is_numeric($value),
        );

        return __($type->messageKey(), $replace);
    }
}
