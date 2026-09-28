<?php

namespace App\Domain\Vtual\Enums;

/**
 * Triage state of an incident.
 *
 * A recurrence **reopens** an `acknowledged` or `resolved` row — the same rule
 * `error_logs` follows, and for the same reason: a resolved incident that fires
 * again is not resolved, and leaving it so hides the regression.
 */
enum IncidentStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
