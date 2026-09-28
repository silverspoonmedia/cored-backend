# vTual — product domain

Content-creator aggregation (`kind => product`, `locked => true`, display name **vTual**). Invariants an agent must obey: [.cursor/rules/vtual.mdc](../../../.cursor/rules/vtual.mdc) and [.cursor/rules/vtual-content.mdc](../../../.cursor/rules/vtual-content.mdc).

vTual keeps a public catalogue of vTubers, their channels and their content, and lets a real account prove it owns a profile by claiming it. Three surfaces: a **guest** directory/feed/landing under `/vtual`, a **member** area for owned profiles, managements and events under `/apps/vtual/*`, and a **staff** area for orphan profiles, the claims queue and platform credentials. Content arrives from the manual YouTube/Twitch clients plus WebSub/EventSub push, is classified into a music layer and a sub-category, and is served to guests through forever-cached landing payloads. Code lives in `app/Domain/Vtual/`; the SPA screens live in the sibling repo.

| Need | Path |
|------|------|
| Code | `app/Domain/Vtual/` |
| Config | [`config/vtual.php`](../../../config/vtual.php) · [`config/products.php`](../../../config/products.php) key `Vtual` · [`config/domains.php`](../../../config/domains.php) |
| API (auth) | `routes/api/v1/vtual.php` |
| API (guest) | `routes/api/v1/vtual-public.php` |
| Realtime channels | `routes/channels.php` — reference § Realtime |
| SPA routes | [`config/spa-routes.php`](../../../config/spa-routes.php) — `vtual.*` under `/apps/vtual/*`; public `vtual.public*` under `/vtual` |
| Menus | [navigation README](../navigation/README.md) — Products → vTual (member) + Administration → vTual (staff) |
| Seeders | `RoleSeeder` → `ArchivdRoleSeeder` → `VtualRoleSeeder` → `MenuSeeder` (this order is mandatory) |
| Tests | `tests/Domain/Vtual/` |
| Pakem | [.cursor/rules/vtual.mdc](../../../.cursor/rules/vtual.mdc) · [.cursor/rules/vtual-content.mdc](../../../.cursor/rules/vtual-content.mdc) |
| Deep dive | [reference.md](reference.md) — identity, claims, credentials · [reference-api.md](reference-api.md) — API + SPA surfaces · [reference-ingestion.md](reference-ingestion.md) — platform clients, pools, ops · [reference-classification.md](reference-classification.md) — music layer, cover detection, merge · [reference-metrics.md](reference-metrics.md) — metric/session history · [reference-capability-grants.md](reference-capability-grants.md) — delegated per-creator grants · [reference-embed-policy.md](reference-embed-policy.md) — third-party embed decision |
| Progress | [implementation-status.md](implementation-status.md) — every row Implemented or Decided (no open work) |
| Naming | [naming-conventions.md](naming-conventions.md) — core role `Management` vs `vtual_managements` |
| History | [ui-archive.md](ui-archive.md) (removed Blade UI) · [archive/](../../archive/) (assessments, legacy audit) |

## Surfaces

| Surface | Route | Audience |
|---------|-------|----------|
| Landing | `/vtual` | Guests — Valkivid3 one-pager: directory, content feed, live strip, events, claim banner. FROZEN |
| Public profile | `/vtual/vtubers/{slug}` | Guests — profile, channels, links, filterable paginated content grid |
| Public management | `/vtual/managements/{slug}` | Guests — org profile, stats, public roster |
| Owned profiles | `/apps/vtual/profiles[/{id}]` | Owner/editor — manage, links, merge, channels |
| Claims hub | `/apps/vtual/claims` | Own claims (any claimant) + staff queue tab (staff only) |
| Claim wizard | `/apps/vtual/vtubers/{slug}/claim` | Any logged-in user — proof channel → token → queued verify |
| Managements | `/apps/vtual/managements` | Org members (invite-only onboarding) |
| Events | `/apps/vtual/events` | Org members — create/update/cancel, no moderation |
| Orphans (staff) | `/apps/vtual/orphans` | Root/Admin/Moderator — claimable profiles |
| Platform credentials | `/apps/vtual/platform-credentials` | Root/Admin — masked CRUD + API probe |
