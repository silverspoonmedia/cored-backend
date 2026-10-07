# YouTube operational stability assessment — 2026-08-07

**Environment:** Laragon local DB + live YouTube HTTP against production `APP_URL` (`https://monolith-ph24.spnd.uk`)  
**Executor:** automated assessment probe (Cursor agent)  
**Reference channel:** `019fdb78-b99f-715c-a701-80b335503861` — 若葉 / Waka (`UCIRQxP7jORi6jsLt0HmUmqQ`)

## Executive summary

| Overall | **Green** (functional pipeline healthy; L7/L8 not Red) |
|---------|--------------------------------------------------------|

YouTube ingestion is **operationally viable** on this environment: credentials valid, gateway probes pass, initial backfill + feed poll + content pool batch correctly, WebSub subscription active with public hub verify, scheduler runs `vtual:*` tasks without chronic `lock_busy`.

**Yellow items** (not blocking at N=1, watch at scale): feed-poll enqueue deadlock under concurrent pool flush (retry succeeded); WebSub `last_notify_at` still null (push path not observed live); content pool `done` rows accumulate (no retention policy yet); `resolve_channel` requires bare handle ( `@handle` returned empty).

**Product gate:** L7 DB performance **Green** at current scale. L8 API quota **Green** — discovery zero-quota, hydrate batched. Neither Red → product worth pursuing at scale with noted follow-ups.

---

## Fase 0 — Environment inventory

| Check | Result |
|-------|--------|
| Migrations | All applied including `2026_08_06_100001` (WebSub + content pool) |
| YouTube platform | `enabled=true` |
| Active credential | 1 row `status=active`, `verified_at` set, `consecutive_auth_failures=0` |
| `APP_URL` | `https://monolith-ph24.spnd.uk` (public HTTPS) |
| `QUEUE_CONNECTION` | `database` (1 stale backfill job processed during assessment) |
| Scheduler | All `vtual:*` entries present in `schedule:list` |

**REF channel (before pipeline):** single active public YouTube channel; `content_initial_backfill_at=null`, `feed_last_polled_at=null`, `last_synced_at=2026-08-07 09:06:01`.

---

## Fase 1 — Credential probe

```
PlatformCredentialService::test → ok=true, message="YouTube API key accepted."
Post-test: status=active, last_error_at=null
```

**Layer L1: Green**

---

## Fase 2 — Gateway probe matrix

| Method | Input | Result |
|--------|-------|--------|
| `fetch_channels` | `UCIRQxP7jORi6jsLt0HmUmqQ` | count=1, snippet + subscriber_count=212 |
| `resolve_channel` | `@wakamayu_ch` | count=0 (empty) |
| `resolve_channel` | `waka377ch` | count=1, same UC id |
| `fetch_videos` | 3 ids from Atom feed | count=3 |
| `fetch_videos_chunked` | same 3 ids | count=3 |

Feed-derived video ids (0 quota): `tedwsGQ6yNY`, `3WVOi9fmgpc`, `Tt_y7u3OBkM`

**Layer L2: Green** (resolve `@` prefix edge case → document as UX/parser follow-up, not gateway outage)

---

## Fase 3 — DB health baseline

### Before pipeline

| Metric | Value |
|--------|-------|
| Active public YouTube channels | 1 |
| Missing `content_initial_backfill_at` | 1 |
| Channel pool rows | 0 |
| Content pool rows | 0 |
| WebSub subscriptions | 1 `active` |
| `vtual_contents` | 0 |
| Quota errors (7d) | 0 |

### After pipeline

| Metric | Value |
|--------|-------|
| Missing backfill | 0 |
| `vtual_contents` | 50 |
| Content pool | 24 `done`, 0 `failed` |
| Channel pool | 0 (processed flushed) |
| WebSub | 1 `active`, lease `2026-08-17` |

---

## Fase 3b — DB performance

### EXPLAIN hot paths (MySQL)

| Query | type | key | Notes |
|-------|------|-----|-------|
| Feed poll cohort | range/ref | `vtual_channels_platform_id_external_id_unique` | No full scan |
| Content pool pending | ref | `vtual_ct_pool_plat_status_req_idx` | Covering index |
| Channel pool pending | ref | `vtual_ch_pool_plat_status_req_idx` | Covering index |
| WebSub lease scan | index | `vtual_websub_status_lease_idx` | Index scan on 1 row |
| Resync stale | ref | platform unique index | Filtered by platform |
| Contents by channel | ref | `vtual_contents_channel_id_status_index` | **Using filesort** on `published_at` (50 rows — negligible now) |

### Pool retention

- 24 `done` rows in `vtual_content_fetch_pool` after one assessment session — acceptable at N=1; at scale need archival/prune policy if `done` grows without bound.

### Scaling projection (@100 active YouTube channels)

| Workload | DB impact | Mitigation in code |
|----------|-----------|-------------------|
| Feed poll every 15m | Cursor over active UC channels | Platform + status filter; index on platform_id |
| Content pool flush 1m | Claim up to 50 pending ids | `(platform_id, status, requested_at)` index |
| Hourly resync | Up to `resync_enqueue_limit=200` stale channels | Predicate on `last_synced_at` / `subscriber_count` |
| Pool `done` growth | Index bloat over months | **Not implemented** — recommend retention job |

**Layer L7: Green** at N=1; **Yellow** at scale until pool `done` retention exists.

---

## Fase 3c — API quota baseline

### Unit budget reference

| Operation | Units/call | Routine use |
|-----------|------------|-------------|
| `channels.list` (≤50 ids) | 1 | Channel pool, credential test |
| `videos.list` (≤50 ids) | 1 | Content pool hydrate |
| `playlistItems.list` (≤50) | 1 | Initial backfill (once per channel) |
| `search.list` | 100 | Manual only — not used in assessment |
| Atom feed GET | 0 | Feed poll |
| WebSub push | 0 API units | Notify path |

### Quota errors (7d)

None matching `quota` / `quotaExceeded`.

### Daily projection (@100 channels, steady state)

| Source | API units/day (order of magnitude) |
|--------|-------------------------------------|
| Atom feed poll (15m × 96) | 0 |
| New video hydrate (1 new video/channel/day, batched) | ~100–200 (worst case 100 single-id batches if spread; best case ~2 batches/min coalesced) |
| Channel resync (hourly, stale only) | ~0–4 (200 cap / 50 batch) |
| Live CCV poll (2m, only when live rows exist) | 0 when no live content |
| `search.list` | 0 (not scheduled) |

Well under default 10k daily quota at 100 channels if batching holds.

**Layer L8: Green**

---

## Fase 4 — Forced pipeline (REF channel)

| Step | Output | Implied API units |
|------|--------|-------------------|
| `flush-channel-pool --force` (initial) | skipped, http=0 | 0 |
| `flush-channel-pool --resync --force` | Resync enqueue 0 (recent sync) | 0 |
| `backfill-initial-content` | listed=50 fetched=50 upserted=50 | ~2 (playlistItems + videos.list) |
| `poll-channel-feeds --force` (1st) | **Deadlock 1213** on pool insert | 0 (failed before complete) |
| `flush-content-pool --force` | claimed=9 upserted=9 failed=0 **http=1** | 1 |
| `fetch-channel-content --limit=5` | listed=5 fetched=5 upserted=5 | ~2 |
| `poll-channel-feeds --force` (retry) | channels=1 enqueued=15 skipped=0 | 0 |
| `flush-content-pool --force` | claimed=15 upserted=15 failed=0 **http=1** | 1 |
| `renew-websub --repair-missing` | 0 renewed, 0 repaired | 0 |
| `poll-live-contents` | polled=0 updated=0 | 0 |
| Enqueue refresh + `flush-channel-pool --force` | applied=1 **http=1** | 1 |

**Batch efficiency:** 15 feed-enqueued videos hydrated in **1** HTTP batch (`http=1`).

**Layer L4: Yellow** — deadlock on concurrent feed poll + content pool insert; retry succeeded.

---

## Fase 5 — WebSub E2E

| Check | Result |
|-------|--------|
| Subscription row | `status=active`, `lease_expires_at=2026-08-17 09:06:01` |
| `last_notify_at` | null (no push observed during assessment) |
| Hub verify (live) | `GET .../youtube-websub?hub.mode=subscribe&hub.challenge=test123&hub.topic=...` → **200** body `test123` |
| Regression tests | 26 passed (`YoutubeWebSub`, `PlatformApiProbe`, `ChannelFeedPoll`, `ContentIngestion`) |

**Layer L5: Yellow** — subscription healthy; push path not demonstrated with real notify.

---

## Fase 6 — Scheduler stability

`schedule:work` observed ~3 minutes (09:28–09:30 UTC+7):

- `vtual:flush-channel-pool` — DONE each minute, no `lock_busy`
- `vtual:flush-claim-verify-pool` — DONE
- `vtual:flush-content-pool` — DONE
- `vtual:poll-channel-feeds` — DONE at :00/:15 boundary (09:30)
- `vtual:poll-live-contents` — DONE every 2m

Pending pool counts after observation: 0 pending (drained).

**Layer L6: Green**

---

## Rubric summary

| Layer | Score | Rationale |
|-------|-------|-----------|
| L1 Credentials | **Green** | Live test OK, active credential |
| L2 Gateway | **Green** | All production methods work; `@handle` edge case |
| L3 Channel cohort | **Green** | Backfill complete post-probe |
| L4 Pools | **Yellow** | Deadlock once; `done` pool rows accumulating |
| L5 WebSub | **Yellow** | Active + hub verify; no live notify |
| L6 Scheduler | **Green** | Stable 3m observation |
| L7 DB performance | **Green** | Indexes used; filesort minor; retention TBD at scale |
| L8 API quota | **Green** | Zero-quota discovery, batch hydrate, no quota errors |

---

## Open risks (not probe failures)

From the (now archived with its domain) vTual implementation status:

- WebSub tombstone / deleted video 404 handling not implemented
- Content pool / channel pool `done` row retention not implemented
- Private discovered channels excluded from feed/WebSub until promote
- Realtime pool flush UI still manual reload

---

## Recommended actions (priority)

1. **Feed poll deadlock** — investigate concurrent insert into `vtual_content_fetch_pool` (feed poll vs flush-content-pool); add retry or lock ordering.
2. **WebSub push validation** — publish test video on REF channel or replay signed notify in staging; confirm `last_notify_at` updates.
3. **Pool retention** — scheduled prune for `done`/`failed` pool rows older than N days (DB perf at scale).
4. **`resolve_channel` `@` prefix** — align parser with `waka377ch` success path.
5. **Queue worker** — ensure `queue:work` runs in prod so `RunChannelInitialContentBackfillJob` does not rely on manual `backfill-initial-content`.

---

## Appendix — config knobs referenced

From `config/vtual.php`:

- `http.batch.videos` / `channels` / `playlist_items` = 50
- `channel_pool.threshold` = 50, `max_wait_minutes` = 15, `resync_enqueue_limit` = 200
- `content_pool.threshold` = 50
