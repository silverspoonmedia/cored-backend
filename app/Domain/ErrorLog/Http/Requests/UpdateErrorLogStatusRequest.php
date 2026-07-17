<?php

namespace App\Domain\ErrorLog\Http\Requests;

use App\Domain\ErrorLog\Models\ErrorLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateErrorLogStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in([
                ErrorLog::STATUS_OPEN,
                ErrorLog::STATUS_RESOLVED,
                ErrorLog::STATUS_IGNORED,
            ])],
        ];
    }
}
