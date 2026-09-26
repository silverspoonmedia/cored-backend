<?php

namespace App\Domain\ActivityLog\Http\Requests;

use App\Domain\ActivityLog\Support\SubjectTypeResolver;
use App\Http\Requests\Concerns\InteractsWithListQueryPaging;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexActivityLogRequest extends FormRequest
{
    use InteractsWithListQueryPaging;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->listQueryPagingRules(), [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'log_name' => ['sometimes', 'string', Rule::in(['user', 'menu', 'auth', 'vtual', 'default'])],
            'subject' => ['sometimes', 'string', Rule::in(app(SubjectTypeResolver::class)->supportedAliases())],
            'subject_id' => ['required_with:subject', 'uuid'],
            'event' => ['sometimes', 'string', 'max:255'],
        ]);
    }
}
