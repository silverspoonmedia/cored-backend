<?php

namespace App\Domain\FileHosting\Enums;

enum FileVariantKind: string
{
    case Ai512 = 'ai_512';
    case Ai1024 = 'ai_1024';

    public function maxPx(): int
    {
        return match ($this) {
            self::Ai512 => 512,
            self::Ai1024 => 1024,
        };
    }

    public static function tryFromConfig(string $kind): ?self
    {
        return self::tryFrom($kind);
    }
}
