<?php

namespace App\Domain\Vtual\Enums;

/**
 * Which integration surface raised an incident.
 *
 * One kind per *failure source*, not per exception class: the triage question is
 * "which integration is broken", and the answer must not change when an
 * underlying client is refactored. Values are stored, so they are a contract —
 * renaming one needs a migration.
 */
enum IncidentKind: string
{
    /** YouTube WebSub subscribe / renew / verify failed. */
    case Websub = 'websub';

    /** Twitch EventSub subscription create / renew / repair failed. */
    case Eventsub = 'eventsub';

    /** Twitch EventSub notification could not be processed (bad signature, unknown channel). */
    case EventsubNotification = 'eventsub_notification';

    /** A platform credential stopped authenticating (401/403, revoked token). */
    case Credential = 'credential';

    /** A platform HTTP call failed after its retry budget was spent. */
    case PlatformHttp = 'platform_http';

    /** Channel / content ingestion failed (feed poll, content pool, hydrate). */
    case Ingestion = 'ingestion';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
