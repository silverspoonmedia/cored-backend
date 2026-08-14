<?php

namespace App\Domain\ErrorLog\Http\Requests;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Http\Requests\Concerns\InteractsWithListQueryPaging;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexErrorLogRequest extends FormRequest
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
        ]);
    }
}
