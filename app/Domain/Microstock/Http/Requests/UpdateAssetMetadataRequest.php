<?php

namespace App\Domain\Microstock\Http\Requests;

use App\Domain\Microstock\Http\Requests\Concerns\MicrostockRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetMetadataRequest extends FormRequest
{
    use MicrostockRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->metadataFinalizeRules();
    }
}
