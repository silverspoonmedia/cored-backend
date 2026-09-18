<?php

namespace App\Domain\Ai\Enums;

/**
 * Lifecycle of an AI connection.
 *
 * - Pending  — created/edited, never successfully probed. Never resolved by consumers.
 * - Active   — probe succeeded (or imported from a working env value). Resolvable.
 * - Disabled — parked by an operator; resolvable only when explicitly re-enabled.
 */
enum AiConnectionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
