# vTual — Legacy broadercastv2 audit (foundation)

One-shot assessment before implementing the **Vtual** product domain in Monolith. Not a living daily guide — the domain was removed on 2026-10-07 (see [vtual-sunset-postmortem.md](vtual-sunset-postmortem.md)); this file is history.

**Sources:** `broadercastv2` source tree; AI analysis files `Result_Sol.md`, `Result_Grok.md`, `Result_GLM.md` treated as **hypotheses** until code-checked. **No legacy secrets or seeder credentials are reproduced here.**

**Date:** 2026-07-22  
**Target:** Monolith product domain `Vtual` (display: vTual), locked, implicit activation.

---

## 1. Project inventory (legacy)

| Item | Value | Evidence |
|------|-------|----------|
| Framework | Laravel 11 / PHP ^8.2 | `composer.json` |
| Auth | Session `web` guard; Spatie roles | `config/auth.php`, `bootstrap/app.php` |
| API surface | Effectively none | `routes/api.php` fully commented |
| Jobs | 0 application classes | no `app/Jobs` |
| Queue | Config default database; unused for aggregation | `config/queue.php` |
| Scheduler | YouTube 4 + Twitch 5 + cleanup/sitemap | `routes/console.php` |
| Kick | None | no code/seeds |
| WebSub / EventSub | None (poll-only) | routes + service repos |
| Migrations | 43 files | `database/migrations/` |
| Models | ~37 | `app/Models/` |
| Service layer | Static `*Repositories` | `app/Repositories/Service/*` |
| Meaningful domain tests | Essentially none | only example PHPUnit stubs |

### Core legacy tables (content path)

| Table | Role |
|-------|------|
| `users` | Auth principal **and** public creator profile |
| `users_link_tracker` | Platform channel (YouTube/Twitch via `base_link`) |
| `users_feed` | Content row; status magic numbers; latest `concurrent` only |
| `base_status` | Seeded status catalog (content: ~7 scheduled, 8 live, 9 archive, 10 VOD) |
| `base_affiliation` / `users_affiliation` | Taxonomy label — **not** talent-management ACL |
| `base_api` | Platform API credentials (plaintext in legacy — **do not port**) |

### Scheduler (confirmed)

```text
YouTube: initial (10m), metadata (5m), checker (3m), fetch-profile (daily)
Twitch:  initial (10m), checker (3m), fetch-profile (daily), fetch-misc (6h), old-feed-cleanup (hourly)
Guards: withoutOverlapping + runInBackground; timezone Asia/Jakarta; no onOneServer()
```

---

## 2. Claim validation ledger

| ID | Source | Claim | Status | Codebase evidence | Notes | Impact on Monolith |
|----|--------|-------|--------|-------------------|-------|--------------------|
| C1 | All three | Poll-only; no WebSub/EventSub/webhooks | **VALID** | `routes/console.php`; zero webhook routes; YT TODO PubSubHubbub in service layer | — | New webhook inbox + queue jobs (post-foundation) |
| C2 | All three | No Kick | **VALID** | no Kick symbols/seeds | — | Defer; platform row `enabled=false` |
| C3 | All three | No application Jobs | **VALID** | no `app/Jobs` | Mail may queue | Real queue workers later |
| C4 | All three | User conflates auth + creator | **VALID** | `User` model + front profile | SGU status id used for system users | Separate `vtual_creators` + membership |
| C5 | All three | “Management” is real org ACL | **INVALID** | `base_affiliation` taxonomy only | Spatie role `Management` is platform ladder label | New `vtual_managements` + members + assignments |
| C6 | All three | Historical CCV / peak / avg | **INVALID** | `users_feed.concurrent` last-write | — | Metric snapshots later |
| C7 | Sol/Grok/GLM | Secrets in seeder / hardcoded API keys | **VALID** | `BaseAPISeeder`, YT/Twitch API repos (do not copy values) | Rotate in legacy ops | Env-only secrets; never port plaintext |
| C8 | All three | No meaningful tests | **VALID** | example tests only | — | Domain unit/feature suite required |
| C9 | All three | Prefer modular rewrite (Option B) | **VALID** as recommendation | domain tangle + poll model | Full framework rewrite unjustified | Product domain inside Monolith |
| C10 | Grok/GLM | Twitch cleanup `dd()` risk | **PARTIALLY_VALID** | reported on `TwitchRepositories` old-feed path | Re-verify if porting any logic | Do not port cleanup as-is |
| C11 | Sol | Broken relations / query precedence bugs | **PARTIALLY_VALID** | analysis cites specific methods | Treat as debt map, not copy targets | Redesign relations with constraints |
| C12 | Grok | Active JSON API | **INVALID** | `routes/api.php` commented | Resources used for DataTables/transforms | Versioned `/api/v1/vtual/*` |

### Cross-analysis agreement

All three analyses agree on: poll-only ingestion, no Kick, no Jobs, User=creator conflation, affiliation ≠ management ACL, secrets risk, Option B modular rewrite. Differences are depth/severity ranking of individual bugs, not product shape.

---

## 3. Gap analysis (legacy → Monolith Vtual foundation)

| Area | Legacy | New requirement | Gap | Recommendation |
|------|--------|-----------------|-----|----------------|
| Module layout | Flat models + static repos | `app/Domain/Vtual/` product locked | Full scaffold | `domain:make Vtual --locked` + DI services |
| Auth | Session web | JWT + `product.active:Vtual` | Wire product registry | Implicit activation |
| Creator identity | = User (+ SGU) | 1 User → N Creator profiles | New tables | `vtual_creators` + `vtual_creator_members` |
| Talent management | Affiliation tags | Org + staff + creator assignment | New tables | `vtual_managements*` |
| Channels | `users_link_tracker` | Multi-platform channels | New table + platforms seed | Composite unique (platform, external_id) |
| Content | `users_feed` + magic status IDs | Typed content + status enums | New table | No live polling in foundation |
| Ingestion | Sync cron poll | WebSub/EventSub + reconcile | Entire subsystem missing | Phase E+ |
| Metrics | Latest concurrent | Snapshots / peak / avg | Missing | Phase E+ |
| Kick | Absent | Optional platform | Blocker until official API decision | Seed disabled platform |
| API | None | Full-stack API + Blade | Missing | Skeleton CRUD foundation |
| RBAC | Admin role gate mostly | Product perms + resource policies | Missing | `VtualRoleSeeder` |
| Docs | Scattered | Living domain guide + archive audit | This file + living README | Monolit doc rules (no flat `docs/*.md` subjects) |

### Reuse classification

| Asset | Classification |
|-------|----------------|
| Domain concepts (channel, feed status lifecycle, multi-channel creator) | **Reusable concept** |
| Status semantics (scheduled/live/archive/VOD) | **Reusable concept** (as enums, not magic IDs) |
| Static repositories / cron controllers | **Reference only / must rewrite** |
| Credential seeders / hardcoded keys | **Unsafe to reuse** |
| Polling intervals as starting guesses | **Reference only** |
| Fanbox / simping / affiliation filters | **Out of foundation scope** |

---

## 4. Decisions locked for foundation (product)

| Decision | Choice |
|----------|--------|
| Creator vs User | Separate profile; 1 User → N Creators via membership |
| Product activation | Implicit (`config/products.php`) |
| Kick | Deferred known limitation; no scraping |
| Implementation scope | Foundation only (schema, CRUD skeleton, RBAC, tests, docs) |
| Doc placement | Archive audit + `docs/domains/vtual/`; not TASK flat root files |

---

## 5. Deferred (explicit non-goals of foundation)

- YouTube WebSub / Data API adapters  
- Twitch EventSub / Helix live poll workers  
- Kick integration  
- Metric snapshot time series  
- Multi-stream session correlation  
- Public homepage / public creator & management profiles (SEO)  
- Paid Service catalog SKUs for Vtual  
- Data migration from broadercastv2  

---

## 6. Related living docs

- [product-activations/README.md](../domains/product-activations/README.md) — runtime gate  
- [platform/architecture.md](../platform/architecture.md) — domain registry  
