<?php

namespace Database\Factories;

use App\Domain\Playground\Models\AttachmentUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttachmentUpload>
 */
class AttachmentUploadFactory extends Factory
{
    protected $model = AttachmentUpload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uploadId = (string) Str::uuid();

        return [
            'disk' => config('attachments.disk'),
            'path' => config('attachments.directory').'/'.$uploadId.'/document.pdf',
            's3_upload_id' => null,
            'strategy' => AttachmentUpload::STRATEGY_SINGLE,
            'original_name' => 'document.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'status' => AttachmentUpload::STATUS_PENDING,
            'parts' => null,
            'user_id' => null,
            'expires_at' => now()->addDay(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => AttachmentUpload::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function multipart(): static
    {
        return $this->state(fn (): array => [
            'strategy' => AttachmentUpload::STRATEGY_MULTIPART,
            's3_upload_id' => 'test-upload-id-'.fake()->uuid(),
            'size' => 16 * 1024 * 1024,
        ]);
    }
}
