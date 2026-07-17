<?php

namespace App\Domain\ErrorLog\Http\Requests;

use App\Domain\ErrorLog\Models\ErrorLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexErrorLogRequest extends FormRequest
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in([
                ErrorLog::STATUS_OPEN,
                ErrorLog::STATUS_RESOLVED,
                ErrorLog::STATUS_IGNORED,
            ])],
            'level' => ['sometimes', 'string', Rule::in([
                'error', 'critical', 'alert', 'emergency',
            ])],
            'type' => ['sometimes', 'string', Rule::in([
                ErrorLog::TYPE_EXCEPTION,
                ErrorLog::TYPE_LOG,
            ])],
            'q' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }
}
