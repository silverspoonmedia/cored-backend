<?php

namespace App\Domain\Archivd\Http\Requests;

use App\Domain\Archivd\Http\Requests\Concerns\ArchivdRequestRules;
use App\Domain\Archivd\Support\Sha256Checksum;
use Illuminate\Foundation\Http\FormRequest;

class StoreHashBlocklistRequest extends FormRequest
{
    use ArchivdRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('sha256')) {
            return;
        }

        $this->merge([
            'sha256' => Sha256Checksum::normalize((string) $this->input('sha256')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sha256BlocklistRules();
    }
}
