<?php

namespace Database\Factories;

use App\Domain\Playground\Models\Attachment;
use App\Domain\Playground\Support\Orders\OrderAttachmentStorage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'file-'.fake()->unique()->numberBetween(1, 100000).'.pdf';

        return [
            'user_id' => null,
            'disk' => OrderAttachmentStorage::DISK,
            'path' => 'orders/attachments/'.fake()->uuid().'.pdf',
            'name' => $name,
            'size' => fake()->numberBetween(1000, 500000),
            'mime' => 'application/pdf',
            'cached_url' => null,
            'cached_url_expires_at' => null,
        ];
    }
}
