<?php

namespace App\Domain\ActivityLog\Http\Resources;

use App\Domain\ActivityLog\Support\CryptoActivityPropertyPresenter;
use App\Domain\ErrorLog\Support\ErrorContextRedactor;
use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Models\Menu;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * @mixin Activity
 */
class ActivityLogResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'event_label' => $this->resolveEventLabel(),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_label' => $this->resolveSubjectLabel(),
            'causer' => $this->resolveCauser(),
            'attribute_changes' => app(ErrorContextRedactor::class)->redact(
                $this->arrayValue($this->attribute_changes),
            ),
            'properties' => $this->resolveProperties(),
            ...$this->dateFields('created_at', $this->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProperties(): array
    {
        $properties = app(ErrorContextRedactor::class)->redact(
            $this->arrayValue($this->properties),
        );

        if ($this->log_name === 'crypto') {
            return CryptoActivityPropertyPresenter::forApi($properties);
        }

        return $properties;
    }

    private function resolveEventLabel(): string
    {
        $event = $this->event ?? $this->description;

        if (! is_string($event) || $event === '') {
            return '';
        }

        $key = 'api/activity-log.events.'.$event;

        return __($key) !== $key ? __($key) : $event;
    }

    private function resolveSubjectLabel(): ?string
    {
        $subject = $this->subject;

        if ($subject instanceof Model) {
            return match (true) {
                $subject instanceof User => $subject->name.' ('.$subject->email.')',
                $subject instanceof Menu => is_array($subject->name)
                    ? ($subject->getTranslation('name', app()->getLocale()) ?: (string) reset($subject->name))
                    : (string) $subject->name,
                default => (string) ($subject->getKey() ?? ''),
            };
        }

        $email = $this->properties?->get('email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCauser(): ?array
    {
        $causer = $this->causer;

        if (! $causer instanceof User) {
            return null;
        }

        return [
            'id' => $causer->id,
            'name' => $causer->name,
            'email' => $causer->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $value->toArray();
        }

        return is_array($value) ? $value : [];
    }
}
