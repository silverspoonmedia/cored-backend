# Microstock daily free trial

Optional per-user daily free AI analyses for product discovery. Enabled via **Settings → Microstock** — no separate trial activation SKU. See [configuration-policy.md](../../platform/configuration-policy.md).

## Configuration

**Settings → Microstock** (`/apps/microstock/settings`):

| Registry key | Config path | Default | Purpose |
|--------------|-------------|---------|---------|
| `microstock.daily_free.enabled` | `microstock.daily_free.enabled` | `false` | Master switch |
| `microstock.daily_free.limit_per_user` | `microstock.daily_free.limit_per_user` | `5` | Max analyses per user per calendar day |
| `microstock.daily_free.timezone` | `microstock.daily_free.timezone` | `UTC` | Calendar day boundary for reset |

On **`APP_ENV=local`**, `migrate:fresh --seed` / `db:seed` applies `enabled=true` and `limit_per_user=1_000_000` via [`LocalSettingsSeeder`](../../database/seeders/LocalSettingsSeeder.php) — prod-safe config literals stay `false` / `5`.

**Env (Tier A — queue names only) + Settings (Tier B — free model aliases):**

| Key | Default | Purpose |
|-----|---------|---------|
| `microstock.ai.vision.free_model` | empty | Free-tier vision alias; empty inherits `microstock.ai.vision.model` — Settings → Microstock |
| `microstock.ai.thinking.free_model` | empty | Free-tier thinking alias; empty inherits `microstock.ai.thinking.model` — Settings → Microstock |

Free queues (lower priority — list after paid queues on workers):

| Env | Default |
|-----|---------|
| `MICROSTOCK_AI_COLLECTION_VISION_QUEUE_FREE` | `microstock-collection-vision-free` |
| `MICROSTOCK_AI_COLLECTION_THINKING_QUEUE_FREE` | `microstock-collection-thinking-free` |
| `MICROSTOCK_AI_SOLO_QUEUE_FREE` | `microstock-solo-free` |

## Behaviour

- **Unit:** one analyzed asset (file) = one slot.
- **Daily-first (Free mode):** collection of 5 files with 2 slots left → 2 `daily_free` + 3 `prepaid`; assignment follows the collection's deterministic filename order.
- **Paid mode bypass:** when the user selects **Paid** at lock time, `AiBillingService::planAnalyze()` skips daily-free allocation entirely and bills every file to prepaid credits, even if free slots remain.
- **Solo mode:** per-file re-analysis has no billing selector; it uses daily-first allocation automatically when daily free is enabled, otherwise prepaid.
- **Free-disabled default:** when `microstock.daily_free.enabled` is off, the billing-mode selector is hidden and all analyses are forced to `paid`.
- **Tier model:** each asset keeps one `billing_source` through vision and thinking. `daily_free` uses the optional `*_MODEL_FREE` aliases; `prepaid` uses the existing paid/default aliases. Empty free aliases inherit paid aliases. Mixed collections therefore use both model pairs without new queues or schema.
- **Accounting:** usage stores the selected alias in `alias_model`; provider pricing still resolves from the response's `resolved_model`, preventing cheap-looking aliases from lying to billing.
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

Model env changes require `php artisan config:rebuild` where config caching is used, then a queue worker restart. Existing workers are long-lived and will otherwise keep old config in memory like a zombie clutching stale aliases.

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

When daily free is enabled, the **Lock & Describe** modal shows a mandatory **Free / Paid** selector. Free mode uses the daily allowance first and falls back to prepaid; Paid mode ignores the daily allowance and bills every file to prepaid credits. The collection billing line in the modal footer updates live as the user toggles the selector.

Daily free allowance also appears on the **Usage & balance** page summary (`GET /apps/file-hosting/microstock/usage`) and in the collection billing line. Free-tier usage rows show `billing_source = daily_free` on the **AI usage detail** tab; they do not appear in the prepaid ledger tab. Root / Admin / Moderator users see daily-free subsidized cost in the **Platform overview** tab. See [Usage & balance UI](README.md#usage--balance-ui).
