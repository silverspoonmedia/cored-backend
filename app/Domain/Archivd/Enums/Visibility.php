<?php

namespace App\Domain\Archivd\Enums;

enum Visibility: string
{
    case Private = 'private';
    case AnyoneWithLink = 'anyone_with_link';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isLinkShared(): bool
    {
        return $this === self::AnyoneWithLink;
    }
}
