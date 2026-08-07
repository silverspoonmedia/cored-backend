<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use Illuminate\Support\Str;

/**
 * Read-only public profile aggregate (no membership gate).
 */
class VtuberProfileService
{
    public function __construct(
        private readonly ContentService $contents,
    ) {}

    public function show(string $key): Creator
    {
        $creator = $this->resolveActive($key);

        $creator->load([
            'channels' => function ($query): void {
                $query->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public)
                    ->with('platform')
                    ->orderBy('created_at');
            },
        ]);

        $creator->setRelation(
            'profileContents',
            $this->contents->listForCreator($creator->id),
        );

        return $creator;
    }

    private function resolveActive(string $key): Creator
    {
        $query = Creator::query();

        if (Str::isUuid($key)) {
            $query->whereKey($key);
        } else {
            $query->where('slug', $key);
        }

        $creator = $query->first();

        if ($creator === null || $creator->status !== EntityStatus::Active) {
            throw new VtualException(__('api/vtual.vtuber.not_found'), 404);
        }

        return $creator;
    }
}
