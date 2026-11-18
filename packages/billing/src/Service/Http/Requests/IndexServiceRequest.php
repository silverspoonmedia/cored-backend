<?php

namespace App\Domain\Service\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithListQueryPaging;
use Illuminate\Foundation\Http\FormRequest;

class IndexServiceRequest extends FormRequest
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
        return $this->listQueryPagingRules();
    }
}
