# vTual Web UI Archive

**Archived:** 2026-08-05  
**Reason:** UI/UX restart — internal staff work begins with orphan vTuber CRUD only (`/apps/vtual/orphans`). External product surfaces and legacy admin Blade UIs are parked, not deleted.

## What was archived

| Bucket | Location |
|--------|----------|
| External web routes | [`routes/web/_archive/vtual.php`](../../routes/web/_archive/vtual.php) |
| Internal admin web routes | [`routes/web/_archive/vtual-admin.php`](../../routes/web/_archive/vtual-admin.php) |
| Blade views | [`resources/views/_archive/adminlte4/vtual/`](../../resources/views/_archive/adminlte4/vtual/) |
| Status badge component | [`resources/views/_archive/adminlte4/components/vtual/`](../../resources/views/_archive/adminlte4/components/vtual/) |
| Web controllers | [`app/Domain/Vtual/Http/Controllers/Web/_archive/`](../../app/Domain/Vtual/Http/Controllers/Web/_archive/) |
| Blade web tests | [`tests/_archive/Feature/Http/Controllers/Web/VtualBladeWebTest.php`](../../tests/_archive/Feature/Http/Controllers/Web/VtualBladeWebTest.php) |
| Platform API probe web test | [`tests/_archive/Domain/Vtual/Feature/Http/Controllers/Web/PlatformApiProbeWebTest.php`](../../tests/_archive/Domain/Vtual/Feature/Http/Controllers/Web/PlatformApiProbeWebTest.php) |

### Vite entries removed from active build

- `resources/css/vtual/admin.css`
- `resources/js/vtual/confirm-modal.js`
- `resources/js/vtual/credential-form.js`
- `resources/js/vtual/registry-admin.js`
- `resources/js/vtual/credentials-platform.js`

Source files remain on disk for reference; they are no longer registered in `vite.config.js`.

### Archived surfaces (summary)

**External (user):** hub, profiles, claims, channel/link management  
**Internal (staff):** registry & pending claims, platform credentials, API probe

## What stays active

- **API** — [`routes/api/v1/vtual.php`](../../routes/api/v1/vtual.php) and all domain services unchanged
- **Settings** — `settings.vtual` menu entry (operational knobs, not archived Blade product UI)
- **New web UI** — [`/apps/vtual/orphans`](../../routes/web/vtual-orphans.php) (orphan vTuber index + CRUD)

## Restoring archived UI

1. Re-register route files in [`routes/web.php`](../../routes/web.php)
2. Move views/controllers back from `_archive/` paths
3. Re-add Vite entries and run `npm run build`
4. Restore MenuSeeder product/admin vTual branches and `config/products.php` `menu_routes`
5. Re-enable archived tests or port assertions to new surfaces

## Reusable patterns from archive

- `ConsumesApi` + `respondFromApi` web proxy pattern
- `x-adminlte4::form.ajax` + Bootstrap confirm modal (`confirm-modal.js`)
- `x-adminlte4::vtual.status-badge` component (in archive)
- Client-side DataTables for small queues (registry/credentials admin)

The new orphan admin intentionally follows the **`/apps/users` server-side DataTable** pattern instead.
