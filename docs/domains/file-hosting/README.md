# File Hosting — product domain

Locked product domain for personal nested folders + S3-compatible chunk upload.

Code: `app/Domain/FileHosting/`. Routes: `routes/api/v1/file-hosting.php`, `routes/web/file-hosting.php`. Config: `config/file-hosting.php`.

## Concepts

| Concept | Behavior |
|---------|----------|
| Virtual root | `folder_id = null` = “My Files” root (no auto DB row) |
| Nested folders | `parent_id` tree per owner; breadcrumb via `FolderAncestorChain` |
| Files | `file_hosting_files` with denormalized `user_id` + optional `folder_id` |
| Upload sessions | `file_hosting_uploads` — direct/multipart presign → complete promotes to file |
| S3 key | `file-hosting/{user_id}/{upload_id}/{slug.ext}` |
| Ownership | Own: actor owns resource. Cross-user: product “any” perm + core `RoleService` hierarchy |

## Permission catalog (`domain_kind = product`)

```
view|create|edit|delete file hosting folder
view|create|edit|delete file hosting file
view any file hosting resource
manage any file hosting resource
```

| Role | Scope |
|------|--------|
| File Hosting Admin / Editor / Viewer | Product ladder (independent of core levels) |
| Core `User` | Own CRUD (seeder pivot) |
| Core Root / Admin / Moderator / Management | Own + any (hierarchy still filters) |

Seeder: `FileHostingRoleSeeder` (after `RoleSeeder`).

## API (`/api/v1/file-hosting/*`, `auth:api`)

| Area | Endpoints |
|------|-----------|
| Browse | `GET browse?folder_id=&user_id=` |
| Folders | `POST folders`, `PATCH …/rename`, `PATCH …/move`, `DELETE`, `POST …/restore`, `DELETE …/force` |
| Files | `GET files/{id}`, `PATCH …/rename`, `PATCH …/move`, soft/force delete, restore, `GET …/download` |
| Upload | `POST uploads`, `GET uploads/{id}`, `POST …/sign-parts`, `POST …/complete`, `DELETE` abort |
| Search | `GET search?q=` |
| Trash | `GET trash` |
| Quota | `GET quota` |

`user_id` query/body: internal actors with “any” + hierarchy may browse another owner’s tree.

## Web Blade

`file-hosting.index` / `file-hosting.trash` — pure `ApiClient` proxy. Chunk widget: `resources/js/file-hosting/direct-upload.js`.

Menu: **My Files** under main sidebar (`file-hosting.index`).

## Tests

`tests/Domain/FileHosting/` — `composer test -- --filter=FileHosting`

## Related

- [product/README.md](../product/README.md) — catalog stub (retired Items UI)
- [identity/README.md](../identity/README.md) — H-RBAC / domain_kind
- [platform/architecture.md](../../platform/architecture.md)
