<?php

namespace App\Domain\Ai\Enums;

/**
 * Wire shape of an AI connection. Only one today — the [OI]-compatible
 * chat-completions shape the platform already speaks.
 */
enum AiConnectionDriver: string
{
    case OpenAiCompatible = 'openai_compatible';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
