<?php

namespace App\Domain\Vtual\Enums;

/**
 * Lifecycle of one row in the `vtual_content_ai_audit` pool.
 *
 * Pending → Processing (claimed by `vtual:audit-content-ai`) → Done | Failed.
 * A Failed row stays in the pool with its `attempts` count; the drainer stops
 * retrying once `--max-attempts` is reached.
 */
enum ContentAiAuditStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
}
