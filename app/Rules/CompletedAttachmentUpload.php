<?php

namespace App\Rules;

use App\Domain\Playground\Models\AttachmentUpload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ensures a staged upload session exists and has completed successfully.
 */
class CompletedAttachmentUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail(__('validation.uuid'));

            return;
        }

        $upload = AttachmentUpload::query()->find($value);

        if ($upload === null) {
            $fail(__('api/playground-attachment-uploads.not_found'));

            return;
        }

        if ($upload->status !== AttachmentUpload::STATUS_COMPLETED) {
            $fail(__('api/playground-attachment-uploads.not_completed'));
        }
    }
}
