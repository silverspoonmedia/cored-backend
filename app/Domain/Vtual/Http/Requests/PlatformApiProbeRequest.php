<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Services\PlatformApiProbeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformApiProbeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::in([
                PlatformKey::Youtube->value,
                PlatformKey::Twitch->value,
            ])],
            'method' => ['required', 'string', Rule::in(PlatformApiProbeService::METHODS)],
            'ids' => ['nullable'],
            'id' => ['nullable', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
            'parts' => ['nullable'],
            'parts.*' => ['string', 'max:64'],
            'chunked' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('ids');
        if (is_string($ids)) {
            $parts = preg_split('/[\s,]+/', $ids) ?: [];
            $this->merge([
                'ids' => array_values(array_filter(array_map(
                    static fn (string $v): string => trim($v),
                    $parts,
                ), static fn (string $v): bool => $v !== '')),
            ]);
        }

        $parts = $this->input('parts');
        if (is_string($parts) && $parts !== '') {
            $list = preg_split('/[\s,]+/', $parts) ?: [];
            $this->merge([
                'parts' => array_values(array_filter(array_map(
                    static fn (string $v): string => trim($v),
                    $list,
                ), static fn (string $v): bool => $v !== '')),
            ]);
        }

        // Convenience: chunked=1 maps to fetch_videos_chunked when method is fetch_videos.
        if ($this->boolean('chunked') && $this->input('method') === 'fetch_videos') {
            $this->merge(['method' => 'fetch_videos_chunked']);
        }
    }
}
