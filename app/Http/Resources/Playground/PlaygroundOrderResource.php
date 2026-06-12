<?php

namespace App\Http\Resources\Playground;

use App\Models\Attachment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class PlaygroundOrderResource extends JsonResource
{
    /**
     * Detail lines are included only when the `details` relation is loaded
     * (show), keeping the list payload light. List rows expose `details_count`
     * via `withCount('details')`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'attachment' => collect($this->attachments ?? [])
                ->map(fn (Attachment $file): array => [
                    'path' => $file->path,
                    'name' => $file->name,
                    'size' => $file->size,
                    'mime' => $file->mime,
                    // Disk-aware: a public disk returns a plain URL; an S3 disk
                    // returns a signed URL cached on the row (minted lazily).
                    'url' => $file->url(),
                ])
                ->all(),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'grand_total' => (float) $this->grand_total,
            'status' => $this->status,
            'notes' => $this->notes,
            'details_count' => $this->when(
                $this->details_count !== null,
                fn (): int => (int) $this->details_count,
            ),
            'details' => PlaygroundOrderDetailResource::collection(
                $this->whenLoaded('details'),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
