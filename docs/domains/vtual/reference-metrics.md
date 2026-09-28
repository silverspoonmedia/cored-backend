# vTual — historical metric snapshots

Append-only history for the point-in-time metric columns (`vtual_channels.subscriber_count`, `vtual_contents.concurrent_viewers`). Code: `app/Domain/Vtual/Services/VtualMetricRecorder.php` + `app/Domain/Vtual/Models/ChannelMetric.php`, `ContentMetric.php`.

The two metric columns are overwritten on every refresh, so before this decision the domain could answer *what is it now* and never *what was it* — the gap `bootstrap-task-2026-08.md:358-361` named. Two product calls blocked the schema: the **sampling interval** and the **retention window**. Both are now decided and shipped.

## Decision

| Call | Decision | Why |
|------|----------|-----|
| Channel sampling interval | **15 minutes** | Subscriber counts move on the order of hours. 15 minutes keeps a 90-day window at ~8.6k rows per channel, and the writers only run when a refresh actually happens, so the bucket is a ceiling on write volume, not a floor. |
| Live CCV sampling interval | **1 minute** | CCV is the entire reason a live-stream chart exists, and the poll already runs every 2 minutes (`vtual.live_poll.interval_minutes`). A one-minute bucket lets the poll and an EventSub `stream.online` push land in the *same* row instead of duplicating a sample for the same moment. |
| Retention | **90 days** | Roughly two quarters of growth history — enough for a "how did this creator grow" read — and keeps a 200-channel deployment under ~2M rows per table. |
| Schema | **Two tables, one row per subject per bucket** | Separate tables because the two series have different subjects, intervals and retention semantics; no polymorphic `metrics` table, which would make every query carry a type discriminator. |
| Storage strategy | **Append-only, no updates** | A metric row is an observation, not a state. Correcting history would make the timeline untrustworthy; a wrong sample is superseded by the next bucket. |

All four knobs are Tier B — Settings → vTual (`vtual.metrics.*`) — so ops can widen retention or coarsen the interval without a deploy.

## Tables

| Table | Model | Unique key | Columns |
|-------|-------|-----------|---------|
| `vtual_channel_metrics` | `ChannelMetric` | `(channel_id, bucket_start)` | `subscriber_count`, `bucket_start`, `captured_at`, `platform_id`, `creator_id` |
| `vtual_content_metrics` | `ContentMetric` | `(content_id, bucket_start)` | `concurrent_viewers`, `bucket_start`, `captured_at`, `channel_id`, `platform_id`, `creator_id` |
| `vtual_content_status_events` | `ContentStatusEvent` | none (append-only log) | `status`, `previous_status`, `occurred_at`, `channel_id`, `platform_id`, `creator_id` |

- `bucket_start` is **floored** to the interval on a wall-clock boundary (`:00`, `:15`, `:30`, `:45`), so buckets do not drift with the first write of the day.
- `captured_at` is the real observation time and is what pruning keys on — a sample cannot survive on a bucket technicality.
- FKs to channel / content / platform **cascade**: a metric row is a number about that subject, and an unattributable row is worse than no row. `creator_id` nulls out instead, so a merged or deleted creator does not erase the series.
- Channel rows soft-delete, so archiving a channel keeps its timeline; only a hard delete cascades.

## Live-session history (the session question, answered structurally)

The second product-blocked row asked *what is a live session, and what is the
aggregate for?* (`bootstrap-task-2026-08.md:351-352`). The first half is already
answered by the schema, so no session table was added:

> **A content row is a session.** The platform's stream session id is the content
> `external_id` (see **Multi-stream session correlation**). One row is therefore
> one broadcast, and a restream is a *different* row, because the platform issues
> a different id.

That leaves the *timeline*, which is what `vtual_content_status_events` stores.

| Call | Decision | Why |
|------|----------|-----|
| Session identity | **`content_id`** | Already unique per broadcast; a synthetic session id would be a second key for the same fact. |
| Restream | **Different session** | Different platform session id ⇒ different `external_id` ⇒ different row. No correlation logic, no "is this the same stream" heuristic. |
| Granularity | **Transitions, not polls** | A six-hour stream polled every two minutes produces two rows (`live`, `ended`), not 180. The recorder compares with the stored status and writes nothing when they match. |
| Multi-channel broadcast | **Not correlated** | The same broadcast carried on two channels is two content rows (two platforms, two external ids). Joining them is the product question the other row left open, and nothing asks for it yet. |

Writers: `ContentService::upsertFromPrimary` (transition into `live` / `ended` from
a poll, an EventSub push or a backfill), `ContentService::endLiveStreamsForChannel`
(the end of the broadcast), `ContentService::markUnavailable` (the tombstone).
`VtualContentStatusRecorder::latestStatus()` answers "where did this row get to"
for callers that did not load it first.

## Writers

`VtualMetricRecorder` is the only writer of the two metric tables, and every call site is a place that already holds a fresh value — no new HTTP, no new scheduled fetch:

| Writer | Seam |
|--------|------|
| `ChannelService::activateFromDto` | new channel row, first snapshot |
| `ChannelActivationService::activatePendingFromDto` | pending → active after batch profile resolve |
| `ChannelFetchPoolService::applyDtoToChannels` | scheduled resync / manual refresh flush |
| `CreatorClaimVerifyPoolService` | claim verification refreshes the snapshot |
| `ContentService::upsertFromPrimary` | live CCV from the poll, an EventSub push or a backfill |

The value defaults to the subject's own column (`recordChannel($channel)` reads `subscriber_count`), so the timeline and the current-value column can never disagree.

## Invariants

- **Bucketed, not throttled.** The metric write is `insertOrIgnore` on the unique index: a burst inside one bucket collapses to one row with no read-then-write and no lock. The **first** sample of a bucket wins — a row never flickers between values.
- **Transitions, not polls.** The status log writes only when the status moved, so poll frequency and table size are independent.
- **Null is skipped, never stored.** A hidden subscriber count would otherwise occupy the bucket and shadow the real value arriving later in the same window. A live row with no CCV is likewise not sampled — "live with unknown viewers" is a state of the *content row*, not a metric.
- **Live only.** `recordContent` ignores non-live rows: a VOD has no concurrent viewers.
- **Best-effort.** Both recorders catch every failure, report it and return. History must never fail the hydrate or the pool flush that owns the transaction. A missing call is therefore silent — `VtualMetricWiringTest` and `VtualContentStatusRecorderTest` exist to pin each seam.
- **Octane-safe.** No static or request state; neither recorder holds a cache between calls.

## Retention

`php artisan vtual:prune-metrics {--days=}` deletes rows older than the window (default `vtual.metrics.retention_days` = 90), in 500-row batches, from all three tables — `captured_at` for the samples, `occurred_at` for the status log; scheduled daily in `routes/console.php`. It follows `vtual:prune-fetch-pools` exactly — same shape, same `--days` override. The timeline shares the metric window on purpose: a transition with no samples left to annotate it is not a report anyone reads.

## Explicitly out of scope

- **No read API, no chart.** This decision is about *keeping* history. Serving it (a metric series endpoint, an SPA chart on the manage page) is a separate surface with its own pagination and caching questions, and nothing reads these tables yet.
- **No per-user metric rows.** The tables are per subject (channel / content), which is what a growth chart needs.
- **No backfill.** History starts when the writers shipped; inventing samples for the past is not a thing this domain can honestly do.
- **No cross-channel session correlation.** One broadcast carried on several channels stays several sessions until a product spec says what the aggregate is for.
