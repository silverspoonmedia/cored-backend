# Microstock daily free trial

Optional per-user daily free AI analyses for product discovery. Enabled via env — no separate trial activation SKU.

## Configuration

| Env | Default | Purpose |
|-----|---------|---------|
| `MICROSTOCK_DAILY_FREE_ENABLED` | `false` | Master switch |
| `MICROSTOCK_DAILY_FREE_LIMIT` | `5` | Max analyses per user per calendar day |
| `MICROSTOCK_DAILY_FREE_TIMEZONE` | `UTC` | Calendar day boundary for reset |

Free queues (lower priority — list after paid queues on workers):

| Env | Default |
|-----|---------|
| `MICROSTOCK_AI_COLLECTION_VISION_QUEUE_FREE` | `microstock-collection-vision-free` |
| `MICROSTOCK_AI_COLLECTION_THINKING_QUEUE_FREE` | `microstock-collection-thinking-free` |
| `MICROSTOCK_AI_SOLO_QUEUE_FREE` | `microstock-solo-free` |

## Behaviour

- **Unit:** one analyzed asset (file) = one slot.
- **Daily-first:** collection of 5 files with 2 slots left → 2 `daily_free` + 3 `prepaid`.
- **Lazy reset:** `microstock_daily_free_usage` row per `(user_id, usage_date)`; no cron credit top-up.
- **Reservation:** slots consumed in `AiBillingService::planAnalyze()` inside a DB transaction; rolls back if prepaid balance is insufficient.
- **Settlement:** `billing_source = daily_free` → provider cost recorded on `microstock_ai_usage_events`, prepaid `debit()` skipped.
- **Failed runs:** consumed daily slots are not refunded (provider cost + abuse prevention).

## Worker order

Process paid queues before free queues. Use the domain worker command (includes `default` for orchestration jobs):

```bash
php artisan microstock:queue-work
```

On Windows/Laragon: `php artisan microstock:queue-work --listen`

## Analytics queries

**Unique users using daily free (today):**

```sql
SELECT COUNT(DISTINCT user_id) FROM microstock_daily_free_usage
WHERE usage_date = CURRENT_DATE AND analyses_used > 0;
```

**Provider cost subsidized by daily free:**

```sql
SELECT SUM(provider_cost_micro_usd) FROM microstock_ai_usage_events
WHERE billing_source = 'daily_free';
```

**Users who hit the limit then purchased credits:**

```sql
SELECT DISTINCT u.user_id
FROM microstock_daily_free_usage u
JOIN microstock_ai_credit_transactions t ON t.user_id = u.user_id
WHERE u.analyses_used >= :limit
  AND t.type = 'purchase'
  AND t.created_at >= u.updated_at;
```

## API

`GET /api/v1/microstock/billing` includes:

```json
"daily_free": {
  "enabled": true,
  "limit": 5,
  "used": 2,
  "remaining": 3,
  "resets_at": "2026-07-29T00:00:00+00:00"
}
```

`GET collections/{folder}/status` includes `billing_tier`: `free` | `paid` | `mixed` while `processing`.

Insufficient prepaid credits (422) may include `daily_free` snapshot in the error `data` payload.

## UI

Daily free allowance appears on the **Usage & balance** page summary (`GET /file-hosting/microstock/usage`) and in the collection billing line. Free-tier usage rows show `billing_source = daily_free` on the **AI usage detail** tab; they do not appear in the prepaid ledger tab. Root / Admin / Moderator users see daily-free subsidized cost in the **Platform overview** tab. See [Usage & balance UI](README.md#usage--balance-ui).
