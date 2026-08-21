# vTual — naming conventions (Management disambiguation)

Canonical guide so **core platform staff roles**, **vTual product RBAC**, and **talent-org entities** never get tangled in code, docs, UI copy, or API paths.

| | |
|--|--|
| Domain guide | [README.md](README.md) |
| Progress | [implementation-status.md](implementation-status.md) |

---

## The problem

English overloads **“management”** in three unrelated places:

1. **Core Spatie role `Management`** — platform H-RBAC staff (Identity ladder), `domain_kind = core`.
2. **vTual product entity** — talent organization (`vtual_managements`), a public/org profile that can roster creators.
3. **Generic English** — “content management”, “channel management”, admin UI labels.

Without fixed vocabulary, agents and humans mix up “Management can manage managements” (sometimes true via API perms, sometimes false for web menus) and write ambiguous UI strings.

---

## Two concepts — never conflate

### A. Core role `Management` (platform staff)

| Aspect | Convention |
|--------|------------|
| **What it is** | Identity **core** role (level 300). Operational staff for the **Monolith platform**, not a vTual talent org. |
| **Spatie** | `roles.name = 'Management'`, `guard_name = api`, `domain_kind = core` |
| **Code reference** | Role name string `'Management'` in seeders/policies, or “core Management role” in prose |
| **UI copy (en)** | **Platform staff** / **internal staff** — never “talent management” |
| **Web vTual menus** | **Not** in `config/vtual.php` `internal_web_roles` (orphan Blade is Root/Admin/Moderator only). API: full vTual product perms via [`VtualRoleSeeder`](../../../database/seeders/VtualRoleSeeder.php) |
| **Related core roles** | `Root`, `Admin`, `Moderator` — see Identity domain |

**Does not:** create rows in `vtual_managements`, represent a talent agency, or imply membership on `vtual_management_members`.

### B. Talent org — `vtual_managements` (product entity)

| Aspect | Convention |
|--------|------------|
| **What it is** | Product-domain **talent organization** — roster of creators, org profile, org staff users. |
| **DB tables** | `vtual_managements`, `vtual_management_members`, `vtual_management_creators` |
| **Eloquent model** | `App\Domain\Vtual\Models\Management` (class name is legacy-short; **always disambiguate in docs**) |
| **API paths** | `/api/v1/vtual/managements` (plural REST noun — product resource, not core role) |
| **Permissions** | `view vtual management`, `create vtual management`, `edit vtual management` (`domain_kind = product`) |
| **UI copy (en)** | **Talent management** or **Management org** — never bare **“Management”** alone in user-facing vTual screens |
| **Member roles** | `vtual_management_members.role`: `owner` \| `admin` \| `staff` — **org-scoped**, not Spatie |

**Does not:** grant core `Management` role automatically; not the same as Creator membership (`vtual_creator_members`).

```text
Core ladder (Identity)          vTual product (talent org)
─────────────────────          ───────────────────────────
Role: Management               Model: Management → vtual_managements
Role: Admin                    ManagementMember → org staff user
Role: Moderator                ManagementCreator → creator roster
```

---

## Naming convention matrix (vTual product)

Use this table when adding code, migrations, routes, lang keys, or docs.

| Layer | Talent org (B) | Core role Management (A) | Creator profile |
|-------|----------------|--------------------------|-----------------|
| **Prose / docs** | talent management, management org, `vtual_managements` | core role Management, platform staff | creator, vTuber profile, `vtual_creators` |
| **PHP class** | `Management`, `ManagementMember`, `ManagementService` | `Role` name check `'Management'` | `Creator`, `CreatorMember` |
| **Route name** (SPA) | `vtual.managements.*` | — (use `role:Root|Admin|…`) | `vtual.vtuber.show`, `vtual.orphans.*` |
| **Lang file** | SPA JSON `web.vtual-managements.*` | reuse Identity / platform strings | server-side leftovers only: `web/vtual-vtuber.php`, `web/vtual-orphans.php` |
| **Permission prefix** | `… vtual management` | `manage any vtual resource` (staff override) | `… vtual creator`, `… vtual channel` |
| **API JSON key** | `management` (resource), `management_id` | — | `creator`, `creator_id` |
| **Forbidden alone** | “Management” in UI without “talent”/“org” context | calling talent org “the Management role” | “User profile” when meaning creator |

### Eloquent `Management` model — code rule

The model class stays `Management` (namespace `App\Domain\Vtual\Models`). In **comments, PRs, and agent prompts**, prefer:

- **Talent management** (entity), or
- **`Vtual Management` model** / **`vtual_managements` row**

Never write “Management manages the creator” without specifying **org staff** vs **core role**.

### `ManagementMemberRole::Admin` vs core `Admin`

| | `ManagementMemberRole::Admin` | Core role `Admin` |
|--|-------------------------------|-------------------|
| Scope | One talent org | Whole platform |
| Table | `vtual_management_members` | `roles` |
| UI label | **Org admin** | **Administrator** (Identity) |

Same pattern for `staff` (org) vs **Moderator** (core) — unrelated enums.

---

## Internal staff web vs product surfaces

| Surface | Path | Audience |
|---------|------|----------|
| **Staff orphan admin** | `/apps/vtual/orphans` | Core Root/Admin/Moderator (`internal_web_roles`) |
| **Platform credentials** | `/apps/vtual/platform-credentials` | Core Root/Admin |
| **vTual settings** | `settings.vtual` | Core Root/Admin |
| **Product hub (placeholder)** | `/apps/vtual/vtuber` | Any logged-in user (`vtual.vtuber.index`) |
| **Read-only creator profile** | `/apps/vtual/vtuber/{slug}` | Any logged-in user (`vtual.vtuber.show`) |
| **Talent org UI** | Not implemented | Future — use **talent** vocabulary in menu labels |

Core role **Management** may call vTual **API** with `manage any vtual resource` but has **no** Administration → vTual sidebar entries today.

---

## Checklist before shipping vTual UI/API text

1. Does “management” mean **talent org**, **core staff role**, or **generic CRUD**? If unclear, rewrite.
2. Menu label for `vtual_managements`: include **Talent** or **Org** (e.g. “Talent managements”, not “Management”).
3. Permission strings stay as seeded (`view vtual management`) — do not rename without migration + seeder.
4. Route/model `Management` is fine in PHP; user-facing copy uses **talent management**.
5. Cross-link this file in PRs that touch `Management*` classes or `managements` routes.

---

## Related

- [README.md — Concepts](README.md#concepts) — entity glossary
- [identity/README.md](../identity/README.md) — core role ladder
- [navigation/README.md](../navigation/README.md) — menu `roles` arrays use Spatie **role names**
