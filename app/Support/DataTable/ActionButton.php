<?php

namespace App\Support\DataTable;

/**
 * Structured action metadata for Bootstrap 4 / jQuery 3 frontends.
 *
 * The client renders each entry as a btn-{style} button and wires method/url/confirm.
 */
final readonly class ActionButton
{
    public function __construct(
        public string $key,
        public string $label,
        public string $method,
        public string $url,
        public string $style = 'secondary',
        public ?string $icon = null,
        public ?string $confirm = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'key' => $this->key,
            'label' => $this->label,
            'method' => strtoupper($this->method),
            'url' => $this->url,
            'style' => $this->style,
        ];

        if ($this->icon !== null) {
            $payload['icon'] = $this->icon;
        }

        if ($this->confirm !== null) {
            $payload['confirm'] = $this->confirm;
        }

        return $payload;
    }
}
