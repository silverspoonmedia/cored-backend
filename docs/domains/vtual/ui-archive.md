# vTual Web UI — historical record

**Status:** the entire Blade surface described here is **deleted**, not parked. This file is kept only to explain why certain vTual features exist in the API but had no UI for a while, and which patterns were deliberately abandoned. Nothing below is a live path — do not follow it as a spec.

**Timeline**

| Date | Event |
|------|-------|
| 2026-08-05 | UI/UX restart. External product surfaces and legacy admin Blade UIs were parked under `_archive/` paths |
| 2026-08 | Platform credentials, API probe, vTuber read/claim profile, and owned profiles list + manage were rebuilt as new Blade surfaces |
| 2026-08 (Blade removal) | Both the archived and the rebuilt Blade surfaces were removed with the rest of the in-repo frontend. `resources/`, `routes/web/vtual-*.php`, and the vTual `Web` controllers no longer exist |

## What the Blade era contained

Archived at the 2026-08-05 restart (paths since deleted): external web routes and legacy admin web routes under `routes/web/_archive/`, Blade views and the status-badge component under `resources/views/_archive/adminlte4/vtual/`, web controllers under `app/Domain/Vtual/Http/Controllers/Web/_archive/`, and two Blade web tests under `tests/_archive/`. Vite registered `vtual/admin.css`, `confirm-modal.js`, `credential-form.js`, and `credentials-platform.js`; `registry-admin.js` was legacy-only and never in the active build.

By surface: **external (user)** was the legacy hub, standalone claims UI, and channel/link management; **internal (staff)** was the registry and pending-claims admin.

## Where those surfaces live now

All of it is SPA (`monolith-vue-workers`). Route names are registered in [`config/spa-routes.php`](../../../config/spa-routes.php) and resolved by the SPA router — see the [vTual README](README.md) for the full table.

| Surface | SPA route name |
|---------|----------------|
| Orphan admin (staff CRUD) | `vtual.orphans.index` / `.create` / `.edit` |
| Owned profiles (list + manage, promote discovered channels) | `vtual.profiles.index` / `vtual.profiles.show` |
| Platform credentials (Root/Admin) | `vtual.platform-credentials.index` / `.create` / `.edit` / `.platform` |
| API probe | `vtual.platform-credentials.probe` |
| vTuber profile (read + claim, login required) | `vtual.vtuber.index` / `.show` / `.claim` |
| Public directory + profile (guest) | `vtual.public` / `vtual.public.vtuber` |

The API ([`routes/api/v1/vtual.php`](../../../routes/api/v1/vtual.php), [`routes/api/v1/vtual-public.php`](../../../routes/api/v1/vtual-public.php)) and every domain service were never archived and are unchanged. The `settings.vtual` menu entry (operational knobs) is likewise unrelated to the removed product UI.

## Patterns deliberately abandoned

Do **not** reintroduce these in Monolith; the SPA has its own equivalents.

- `ConsumesApi` + `respondFromApi` web proxy — the SPA calls `/api/v1/*` directly
- `x-adminlte4::form.ajax` + Bootstrap confirm modal (`confirm-modal.js`) — SPA components handle confirmation
- `x-adminlte4::vtual.status-badge` — domain-local status styling lives in the SPA; see the anti-pattern note in [vue-workers-spa.md](../../integration/vue-workers-spa.md)
- Client-side DataTables for small queues — server-side list modes are the contract, see [list-display-modes.md](../../platform/list-display-modes.md)
