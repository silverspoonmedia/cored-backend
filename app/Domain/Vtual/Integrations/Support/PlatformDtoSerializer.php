<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\Dto\PlatformStreamDto;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use Illuminate\Support\Collection;

final class PlatformDtoSerializer
{
    /**
     * @return list<array<string, mixed>>|array<string, mixed>|null
     */
    public static function serialize(mixed $result): mixed
    {
        if ($result instanceof Collection) {
            return $result
                ->map(fn (mixed $item): mixed => self::serializeOne($item))
                ->values()
                ->all();
        }

        return self::serializeOne($result);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function serializeOne(mixed $item): ?array
    {
        if ($item === null) {
            return null;
        }

        if ($item instanceof PlatformVideoDto
            || $item instanceof PlatformChannelDto
            || $item instanceof PlatformStreamDto) {
            return $item->toArray();
        }

        if (is_array($item)) {
            return $item;
        }

        return ['value' => (string) $item];
    }
}
