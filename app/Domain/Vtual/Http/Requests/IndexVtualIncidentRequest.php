<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Enums\IncidentStatus;
use App\Http\Requests\Concerns\InteractsWithListQueryPaging;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVtualIncidentRequest extends FormRequest
{
    use InteractsWithListQueryPaging;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('q') && $this->filled('search')) {
            $this->merge(['q' => $this->input('search')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->listQueryPagingRules(), [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'status' => ['sometimes', 'string', Rule::in(IncidentStatus::values())],
            'severity' => ['sometimes', 'string', Rule::in(IncidentSeverity::values())],
            'kind' => ['sometimes', 'string', Rule::in(IncidentKind::values())],
            'platform_id' => ['sometimes', 'uuid'],
            'channel_id' => ['sometimes', 'uuid'],
            'credential_id' => ['sometimes', 'uuid'],
            'q' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);
    }
}
