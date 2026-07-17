<?php

namespace App\Domain\FileHosting\Http\Requests;

use App\Domain\FileHosting\Http\Requests\Concerns\FileHostingRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyRequest extends FormRequest
{
    use FileHostingRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->bulkIdsRules();
    }
}
