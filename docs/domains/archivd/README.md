# Archivd — product domain

Personal and team file storage (`kind => product`, `locked => true`). Invariants an agent must obey: [.cursor/rules/archivd.mdc](../../../.cursor/rules/archivd.mdc). Microstock is registered as its **subproduct** (`'parent' => 'Archivd'`), so Archivd cannot be removed while Microstock is still registered.

Archivd is a Drive-lite product: nested folders per owner, chunked S3-compatible upload, link sharing for paid owners, and paid team workspaces. The control plane (init / sign-parts / complete / abort, quota, signed download URLs) always stays in Monolith; the browser's byte path goes either direct to S3 or through the Cloudflare Gateway Worker. The authenticated UI is the SPA; guest share links stay on the server `web` routes. Code lives in `app/Domain/Archivd/`; storage policy is per-disk config.

| Need | Path |
|------|------|
| Code | `app/Domain/Archivd/` |
| Config | [`config/archivd.php`](../../../config/archivd.php) · [`config/filesystems.php`](../../../config/filesystems.php) |
| API | `routes/api/v1/archivd.php` |
| Web (guest share) | `routes/web/archivd-public.php` → `/s/f/{uuid}`, `/s/d/{uuid}` |
| SPA routes | `archivd.*` in [`config/spa-routes.php`](../../../config/spa-routes.php) → `/apps/archivd/*` (index, folder, show, trash, blocklist, workspaces, microstock credentials/usage) |
| Gateway (sibling repo) | `c:\laragon\www\gateway` — upload proxy + Vault download; `S3_TARGETS` is a Worker secret |
| Seeders | `ArchivdRoleSeeder` (before `MenuSeeder`) |
| Tests | `tests/Domain/Archivd/` |
| Pakem | [.cursor/rules/archivd.mdc](../../../.cursor/rules/archivd.mdc) |
| Deep dive | [reference.md](reference.md) — concepts, visibility, permissions, quota · [reference-upload.md](reference-upload.md) — upload pipeline, S3/Gateway, checksums, public share · [reference-realtime.md](reference-realtime.md) — live listing channels, SPA shell, soft-nav rules |
| Subproduct | [microstock README](../microstock/README.md) — AI asset management on an Archivd system workspace (`'parent' => 'Archivd'`) |

## Surfaces

| Surface | Behavior |
|---------|----------|
| Auth app | SPA `/apps/archivd` — browse, upload, share, trash, DMCA blocklist, workspaces |
| Guest share | `/s/f/{uuid}`, `/s/d/{uuid}` — only when the storage owner is **paid** |
| Public landing | `/archivd` reserved for a future promo landing (not the app) |
| Analytics | `HostedFile` events + `WorkspaceService::create` + `FolderRepository::restore` write to `product_analytics` |
