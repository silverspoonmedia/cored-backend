# Archivd - SPA shell & live listing

> Part of the Archivd deep dive. Siblings: [reference.md](reference.md) - concepts, visibility, permissions, quota · [reference-upload.md](reference-upload.md) - upload pipeline, S3/Gateway, checksums, public share.

## Historical Blade web (removed)

The tables and Vue-island notes below describe the **deleted** in-repo Blade + Vite island. Do not recreate them in Monolith. Live browse/trash/show/workspaces are SPA routes (`archivd.index`, `.trash`, `.show`, `.workspaces`, `.blocklist.index`). Guest share stays on Laravel `web`: `/s/f/{uuid}`, `/s/d/{uuid}`.

| Route name | Auth | Purpose |
|------------|------|---------|
| `archivd.index` | yes | Browse + chunk upload; list/grid; Drive-like select/context/drag; bulk **Move**/share/unshare/delete; SPA soft-nav (no hard reload on mutations) |
| `archivd.files.show` | yes | Detail, stats tiles, visibility, download CTA |
| `archivd.files.download` | yes | Auth download -> **302** storage (Worker or S3). Calls `FileService::download` directly (no HTTP loopback); `?preview=1` yields `inline` disposition for safe MIME |
| `archivd.files.rename` | yes | Rename file (AJAX or form) |
| `archivd.files.move` | yes | Move file to folder (AJAX) |
| `archivd.files.visibility` | yes | Toggle private / anyone_with_link |
| `archivd.files.bulk-visibility` | yes | Bulk set visibility (`ids[]`, `visibility`) — AJAX |
| `archivd.files.bulk-destroy` | yes | Bulk soft-delete (`ids[]`) — AJAX |
| `archivd.files.restore` | yes | Restore from trash |
| `archivd.files.force-destroy` | yes | Permanently delete |
| `archivd.folders.rename` | yes | Rename folder |
| `archivd.folders.move` | yes | Move folder |
| `archivd.folders.visibility` | yes | Toggle folder visibility |
| `archivd.folders.bulk-visibility` | yes | Bulk set visibility (`ids[]`, `visibility`) — AJAX |
| `archivd.folders.bulk-destroy` | yes | Bulk soft-delete (`ids[]`) — AJAX |
| `archivd.folders.restore` | yes | Restore from trash |
| `archivd.folders.force-destroy` | yes | Permanently delete |
| `archivd.trash` | yes | Soft-deleted items + restore / force-delete; JSON when `Accept: application/json` |
| `archivd.quota` | yes | JSON soft-refetch for the quota bar (`Accept: application/json`) |
| `archivd.workspaces.members.index` | yes | JSON members list for team offcanvas soft-refetch |
| `archivd.workspaces.activity` | yes | JSON activity feed for team offcanvas soft-refetch |
| `archivd.search` | yes | AJAX JSON search endpoint (`GET ?q=`) |
| `archivd.public.files.show` | no | `/s/f/{uuid}` guest page |
| `archivd.public.files.download` | no | **Temporary signed** hop -> **302** storage (unsigned **403**); `?preview=1` yields `inline` for safe MIME |
| `archivd.public.folders.show` | no | `/s/d/{uuid}` guest folder list |

| `public_download_signature_ttl_minutes` | Laravel signed hop for `/s/f/.../download` (Monolith hop only; storage URL has its own TTL) |

### UI architecture / SPA shell (historical)

The in-repo Vite Vue 3 island (`#vue-archivd`, `FileManagerController` BFF) has been **removed**. The notes below are archive-only; the live shell is `monolith-vue-workers`.

**Fair Vue boundary (as reasoned then):** a *Vue island* was a page-level mount with its own lifecycle — not every helper. Auth browse was one island; pure helpers (router, upload math, `moveItemsRequest`, Echo glue, selection helpers) stayed plain JS, and interactive leftovers under auth browse became components/composables on that island (`useArchivdUpload`, `useArchivdDragMove`). Public `/s/*` stayed vanilla. The standalone Microstock usage page was never an island.

**First paint (Blade era):** Blade supplied AdminLTE chrome plus an empty `#vue-archivd` mount and a JSON bootstrap script; listing/toolbar/upload were client Vue, not SSR rows. All of that is gone — the SPA owns first paint now.

The asset table below records what the removed island was made of. **None of these paths exist**; they are here only to explain how behavior migrated.

| Asset (deleted) | Role |
|-------|------|
| `resources/css/archivd/archivd.css` | BEM-scoped styles (`.ar-*`), theme-aware via `--ar-*` tokens + `[data-bs-theme="dark"]` |
| `resources/js/archivd/router.js` | Client router: parse auth Archivd paths via `/archivd` segment (prefix-agnostic, works under `/apps/…`) + public `/s/*`; `resolveArchivdBase()` (Blade `archivdBase` → `browseUrl` → location → `''`); `joinArchivdPath()` fail-closed joins; intercept in-app links; **pending-nav queue** while busy (latest intent wins — rapid clicks / back-forward are not swallowed) |
| `resources/js/archivd/selection.js` | Pure Drive selection helpers (`listingKeys`, `rangeKeys`, `applyBrowseClick`) — used by Vue `App.vue` |
| `resources/js/vue/islands/archivd/main.js` | Vite entry: bootstrap guard + `mountIsland('#vue-archivd', App)` — loaded from index/trash/show Blade |
| `resources/js/vue/islands/archivd/App.vue` | **Vue 3 SFC shell:** `bootstrap` prop (`config`, `browse`, `trash`, `showFile`, `quota`, `workspaces`, `ui`, `upload`). `useArchivdShell` + `useArchivdMutations` + `useArchivdDragMove`; Archivd router; Echo via `initArchivdRealtime`; Microstock panel/usage when Blade offcanvas DOM exists; soft-nav browse/trash/show; bulk bar; context menu; Vue modals (folder/rename/share/move); Drive select/gestures + drag-move onto folder/breadcrumb/parent `...`. Blade Bootstrap surfaces (`#ArCollectionDescribeModal`, `#arMicrostockPanel`, `#arTeamOffcanvas`, `#ArCreateWorkspaceModal`) via `window.bootstrap` |
| `resources/js/vue/islands/archivd/composables/` | `useArchivd` (BFF fetch), `useArchivdShell` (`hasBrowseChrome` / soft-nav gate), `useArchivdMutations`, `useArchivdUpload` (Vue queue UI), `useArchivdDragMove` (gestures → `moveItemsRequest`) |
| `resources/js/vue/islands/archivd/components/` | `ArIcon.vue`, `ArDropzone.vue` (Vue-owned queue + OS drop; props `softRefresh` / `upsertFile` — no `window.Archivd*` bridge) |
| `resources/js/archivd/realtime.js` | Dedicated Archivd Echo subscribe (user/workspace private channels), health + fallback poll; `browseUrlWithContext` / `fetchJson` |
| `resources/js/archivd/direct-upload.js` | Pure helpers + legacy `initDirectUpload` (auto-init skips `[data-ar-vue-upload]`); auth island uses `run-direct-upload.js` via `useArchivdUpload` |
| `resources/js/archivd/run-direct-upload.js` | Framework-agnostic init→PUT→complete protocol shared by Vue upload |
| `resources/js/archivd/drag-move.js` | `Ar_ITEMS_MIME`, `canDropOnFolder`, `moveItemsRequest` — stay JS; gestures live in Vue |
| `resources/js/archivd/microstock-panel.js` / `microstock-usage.js` / `microstock-ui.js` / `collection-describe.js` | Microstock review/usage/helpers — wired from Vue when DOM present (Blade offcanvas factories OK; not separate islands) |
| `resources/js/archivd/public-app.js` | Public soft SPA for `/s/f/{id}` + `/s/d/{id}` (vanilla; optional future island only if deliberately invested) |
| `resources/js/archivd/upload-progress.js` / `upload-queue.js` / `part-uploader.js` | Pure upload helpers — stay JS |
| `resources/views/adminlte4/archivd/partials/` | Blade shells that surrounded the island: `workspace-modals`, `microstock-review-panel`, `usage-offcanvas`, `file-folder-modals`, `team-offcanvas`, `move-modal`, `file-icon`, `visibility-badge`, `microstock-badge`, `quota-bar`, `workspace-switcher`. Inventory of the whole removed set: [partial-manifest.md](../../web/partial-manifest.md) |

#### Soft-nav rules (auth)

| Route | View panel | Data |
|-------|------------|------|
| `/apps/archivd` (+ `folder_id`, `workspace_id`, `user_id`) | `browse` | `GET archivd` Accept JSON (existing browse payload) |
| `/apps/archivd/trash` | `trash` | `GET archivd/trash` Accept JSON |
| `/apps/archivd/files/{uuid}` | `show` | `GET archivd/files/{uuid}` Accept JSON (**dual response**) |
| Workspace switcher links | browse + Echo rebind | same browse JSON; channel flips `user.*` ↔ `workspace.*` |

**Always full navigation:** file download hops (`…/download` → signed storage URL: Worker HMAC or S3), non-Archivd routes, plans/services links, and **cold trash/show → browse** when `hasBrowseChrome(config)` is false — gate is **non-empty `config.workspacesUrl` only** (`useArchivdShell`). Cold trash/show bootstraps omit that URL (and strip upload/ui chrome); soft-nav to browse would keep stripped config, so the island forces a full document load. Warm path (land on index first — index injects `workspacesUrl` — then soft-nav trash/show and back) keeps soft-nav.

**Mutations (historical Blade island):** all Archivd forms/buttons used `fetch` + `Accept: application/json`. The island forwarded API `data` so the shell could soft-update. Live mutations hit `/api/v1/archivd/*` from the SPA.


#### Drive-like listing interactions (browse only)

| Gesture | Behavior |
|---------|----------|
| Click row/card (not checkbox/actions) | Select only that item |
| Ctrl/Cmd+click | Toggle multi-select |
| Shift+click | Range select in visible list/grid order |
| Click name/icon | Select (does **not** open) |
| Double-click name/icon/row | Open (folder browse / file show via SPA router) |
| Right-click item | Select if needed + context menu (Open, Rename, Move, Share, Delete) |
| Drag selected onto a **folder** row/card | Move into that folder (server auto-renames display-name collisions) |
| Drag selected onto **breadcrumb** parent / root chip | Move up (`data-ar-drop-folder`; root → `folder_id`/`parent_id` null) |
| Nested listing first row/card **`...`** | Virtual parent folder (not a real model): open = go up; drag onto it = move up; no checkbox/bulk/rename/share/delete |
| Escape | Clear selection + close context menu |

Checkbox + bulk bar (**Move** / share / unshare / delete) and per-row toolbar remain. Trash/public pages are out of scope.

#### Action surface parity (single ↔ bulk)

Listing mutations must stay **in lockstep** across surfaces. When shipping a new mutating action, implement **both** per-row/context **and** bulk (bar + multi context) unless the action is inherently single-entity — then mark **N/A** here.

| Action | Single row / context (1 item) | Bulk bar / multi context | Notes |
|--------|-------------------------------|--------------------------|--------|
| Open | yes | N/A | navigation only |
| Rename | yes | N/A | needs one name |
| Move | yes | yes (bar + multi context + drag) | sequential `PATCH …/move`; display-name auto-rename on collision |
| Share / Make private | yes | yes | bulk visibility endpoints |
| Delete | yes | yes | bulk destroy endpoints |

**Agent/dev rule:** do not add a row-only mutator and leave the bulk bar lagging. Open/Rename stay N/A by design; everything else that changes storage state needs a multi-select path (or an explicit table update explaining why not).

**Busy UI:** mutations (move/delete/forms/bulk/context share-delete) toggle `.is-ar-busy` + centered spinner overlay (distinct from soft-nav `.is-ar-loading`).

**Upload queue:** staged rows show human file size next to the name (`File.size` at pick time). Successful (**Done**) rows leave the queue after ~5s (`scheduleDoneRowDismiss` + `.is-dismissing` fade); **failed** rows stay so the user can see what went wrong.

**Manual QA:** single select highlight; ctrl multi; shift range; dblclick open; context rename/move/share/delete; bulk bar Move multi-select; drag file→folder; drag onto parent/root breadcrumb or listing `...` parent row; nested empty still shows `...`; root has no `...`; drag multi; refuse drop on self; spinner on move/delete; upload row shows size; OS file drop still uploads.
**Shell markers:** `[data-file-manager][data-ar-spa]`, panels `[data-ar-panel="browse|trash|show"]`, breadcrumb `[data-ar-breadcrumb]`, trash body `[data-ar-trash-body]`.

#### Soft-nav rules (public)

| Route | Soft? | Notes |
|-------|-------|-------|
| `/s/f/{uuid}` | Yes | `PublicShareController::showFile` dual JSON |
| `/s/d/{uuid}` | Yes | `PublicShareController::showFolder` dual JSON |
| `/s/f/{uuid}/download` | No | signed hop → storage (Worker or S3) |
| Unpaid owner / private | 404 | paid gate stays server-side (`VisibilityService`) |

#### Web BFF JSON contracts

| Endpoint | Accept JSON |
|----------|-------------|
| `GET archivd` | `{ data: browse }` |
| `GET archivd/trash` | `{ data: trash }` |
| `GET archivd/files/{id}` | `{ data: file resource }` |
| `GET archivd/workspaces` | `{ data: workspace[] }` (switcher soft rebuild) |
| `GET archivd/quota` | `{ data: quota }` |
| Mutations (`POST`/`PATCH`/`DELETE` …) | `{ status, message, data?, redirect? }` — SPA ignores redirect when shell bound |
| `GET /s/f/{id}`, `GET /s/d/{id}` | `{ data: … }` guest payload |

Auth index/trash/show Blade injects JSON bootstrap (`data-vue-archivd`) with permissions/labels/URLs and first-paint snapshots. After mount, listing/trash/show paint through **one Vue path** (`useArchivdShell` soft-refetch helpers). Permissions/labels come from that bootstrap `config` (not a Blade `data-config` listing shell).

**Upload progress UI (client-only):** Monolith never sees PUT body bytes (direct S3 or Worker). Progress % = `uploadedBytes / file.size` where `uploadedBytes` is the **sum of per-part loaded bytes** (a Map keyed by part number, so parallel re-PUTs and out-of-order completion never double-count). Speed = a **sliding-window (5s) instantaneous rate** via `createRateSampler` — not a cumulative average — so it recovers after a stall instead of drifting down for the rest of the upload. Multipart parts upload **in parallel** (`max_concurrent_parts`, default 3 XHR streams via `uploadPartsParallel` + `runWithConcurrency`) — a strictly sequential single stream collapses to per-connection throughput on long files (the 20→10→1 MB/s decay). Init presigns **every** part upfront (`presign_ttl_minutes`, default 60); a PUT that returns **403 (expired URL)** is re-signed and retried once per part. After the last part PUT, the row shows **Finalizing…** (`upload.completing`) at 100% with speed cleared while the client calls `POST …/complete` (merge parts / promote `HostedFile`) — then **Done**. Successful rows stay ~**5s** (`DONE_ROW_DISMISS_MS` via `scheduleDoneRowDismiss`) with a short fade (`.is-dismissing`), then leave the queue and free the `active` slot so the list stays uncluttered; **failed** rows stay so the user can see what went wrong. Ongoing (pending / uploading / finalizing) rows always stay. API session payloads do not include progress or speed fields.

Live mutations go through `/api/v1/archivd/*`. The historical Blade island used `FileManagerController` + `ConsumesApi` as a web BFF — that path is gone.

File type icons map MIME -> Bootstrap Icons via `file-icon.blade.php` partial: image, PDF, video, audio, archive, spreadsheet, document, code, fallback — each with a color modifier (`.ar-icon--*`).

Grid/list view toggle persists preference in `localStorage` key `ar-view-mode`. Default: list (table).

Row identity for live patch: `data-ar-entity` (`file`|`folder`) + `data-ar-id` on table rows and grid cards. Quota mount: `[data-ar-quota]`. Show counters: `[data-ar-stat="views|downloads"]`. Team offcanvas: `[data-ar-members-body]`, `[data-ar-activity-list]`.

Sidebar: **Products → Archivd → My Files** (`archivd.index`). The Archivd parent stores the same product-bound route for Domain Access and activation; presented navigation masks it to `#` while children remain visible.

## Realtime / live listing

Archivd listing stays live via **dedicated private channels**. It does **not** use the notification bell, `NotificationType`, or listing registry `table_id` (those remain for `/apps/users` and similar admin lists — see [notification/README.md](../notification/README.md)).

**With the SPA shell:** Echo events invalidate the current view; the client soft-refetches JSON and re-paints via the same helpers as post-mutation success ([UI architecture / SPA shell](#ui-architecture--spa-shell-historical)). Router owns in-app navigation; download hops stay full page.

### Gate

| Check | Behavior |
|-------|----------|
| `BroadcastConnection::isConfigured()` | Pusher/Reverb credentials present -> events may broadcast |
| Platform notification delivery | **Independent.** Archivd listing can use websockets while the bell stays on HTTP polling (`platform.notifications.delivery` in Settings → Platform) |
| Client config | `BroadcastConnection::socketClientConfig()` / `ArchivdRealtime::clientConfig()` — same public fields as the bell, without requiring notification realtime mode |

When unconfigured, services still complete business work; no broadcast is dispatched. The browser falls back to a visible-tab soft-refetch poll (~60s after ~30s degrade grace), same health shape as the notification docs.

### Channels (`routes/channels.php`)

| Channel | Auth | Payload fan-out |
|---------|------|-----------------|
| `private-archivd.user.{userId}` | `$user->id === $userId` | Personal drive listing + storage-owner **quota** pings |
| `private-archivd.workspace.{workspaceId}` | `WorkspaceAccessService::canView` (active member/owner/staff view) | Team listing, members, activity, team file stats |

Personal resources (`workspace_id` null) -> user channel of storage owner. Team resources -> workspace channel only (not every member's user channel). **SPA contract:** the browse view (`ArchivdView.vue` via `useArchivdRealtime.ts`) joins **both** channels — the user channel always, plus `archivd.workspace.{id}` for the active workspace (follows `route.query.workspace_id`; released on unmount). Missing the workspace join is what used to leave other browser tabs stale after an upload in one tab. Both are `createRealtimeChannel` bindings on `src/composables/echo/channelRegistry.ts`: the workspace name is applied with `retarget('archivd.workspace.{id}')` and the registry defers the attach until Echo exists, so a view mounting before `initEcho()` is not left deaf. The view registers `onEchoResync` to re-pull listing + quota after a socket outage.

### Events (`app/Domain/Archivd/Events/`, `ShouldBroadcastNow`)

| Event | `broadcastAs` | Payload (contract) |
|-------|---------------|--------------------|
| `ListingChanged` | `listing.changed` | `action`, `entity_type` (`file`\|`folder`), `entity_id`, `entity_ids[]` (bulk), `owner_user_id`, `workspace_id`, `folder_id`, `from_folder_id`, `actor_id`, optional `resource` snapshot |
| `QuotaChanged` | `quota.changed` | `owner_user_id` (client soft-refetches quota JSON) |
| `WorkspaceMembersChanged` | `members.changed` | `workspace_id`, `action` (`invited`\|`joined`\|`removed`\|`role_changed`\|`archived`), `user_id`, `actor_id` |
| `WorkspaceActivityAppended` | `activity.appended` | `workspace_id`, `actor_id` (invalidation; client refetches activity) |
| `FileStatsChanged` | `file.stats.changed` | `file_id`, `view_count`, `download_count` (show page only) |
| `CollectionAnalysisProgress` (Microstock) | `collection.analysis.progress` | `folder_id`, `workspace_id`, `owner_user_id`, `status`, `phase`, `billing_tier`, `total`, `processed`, `failed`, … |
| `AssetMetadataProgress` (Microstock) | `asset.metadata.progress` | `file_id`, `folder_id`, `microstock` snapshot (`status`, `stages`, `phase`, …) |
| `MicrostockBillingChanged` (Microstock) | `microstock.billing.changed` | `owner_user_id`, `action` (AI credit balance soft-refetch) |
| `MicrostockCredentialsChanged` (Microstock) | `microstock.credentials.changed` | `action`, `credential_id` (owner credential list soft-refetch; no secret data) |

**Bulk ops:** one `listing.changed` with `action: bulk_*` + `entity_ids[]` (invalidation / soft-refetch), not N broadcasts.

Helper: `App\Domain\Archivd\Support\ArchivdRealtime` — single gate + resource snapshot + activity fan. Microstock AI progress uses `App\Domain\Microstock\Support\MicrostockRealtime` on the same channels (throttled ~1 s per folder/asset). Services emit **after successful persist only** (no Eloquent observers for business mutations).

### Emit points (side-effect only; business rules unchanged)

| Service | When |
|---------|------|
| `FileService` | visibility, rename, move, soft/force delete, restore, bulks; view/download -> `FileStatsChanged` only |
| `FolderService` | create, visibility, rename, move, soft/force delete, restore, bulks |
| `DirectUploadService` | initiate/abort -> quota; complete (after commit) -> listing `create` + quota |
| `WorkspaceService` | invite, accept, role change, remove, archive -> members + activity |

### Client strategy

1. Inject `realtime` (`broadcast`, `userId`, `enabled`, `page`, …) into the Vue island bootstrap (`config.realtime` on index / trash / show) when socket config exists.
2. Prefer **one** browser Echo (`window.__monolithEcho`) shared with the notification bell and payment checkout (`checkout.js` / `PaymentRealtime`) when those surfaces are active; Archivd creates its own Echo when the bell is absent.
3. Subscribe to personal or workspace channel based on `workspaceId` / `ownerUserId`.
4. **Apply:** soft-refetch browse/trash/show/quota/members/activity JSON via `useArchivdShell` helpers (correctness floor). Listing patch is **not** used — Vue wires `onListingPatch: () => false`. Row identity attrs (`data-ar-entity` / `data-ar-id`) remain for drag-move / a11y, not for a live DOM-patch path.
5. Replace hard `location.reload()` after mutations/upload complete with soft-refetch when the island is bound.
6. Health: WS primary → 30s grace → visible-tab poll ~60s; reconnect stops poll + one catch-up soft-refetch.

### Not live

- S3 **byte** upload progress (still client-only XHR; peers do not see mid-upload bytes).
- Public share pages (`/s/f`, `/s/d`) — guest SSR, no authenticated Echo.
- Notification bell unread / listing registry `table_id`.

