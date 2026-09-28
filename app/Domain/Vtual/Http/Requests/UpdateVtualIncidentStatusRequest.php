<?php

namespace App\Domain\Vtual\Http\Requests;

use App\Domain\Vtual\Enums\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Triage transition. Only the three triage states are settable here — a
 * recurrence is what reopens an incident, never a manual `open`.
 */
class UpdateVtualIncidentStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(IncidentStatus::values())],
        ];
    }
}
