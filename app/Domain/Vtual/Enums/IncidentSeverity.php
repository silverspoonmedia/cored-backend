<?php

namespace App\Domain\Vtual\Enums;

/**
 * How loudly an incident should be treated during triage.
 *
 * `critical` is reserved for a broken integration that stops data flowing
 * (a revoked credential, a dead subscription), `warning` for a degraded path
 * that a retry or the next poll may clear, and `info` for a signal worth
 * recording without alerting anyone.
 */
enum IncidentSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
