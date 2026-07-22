# vTual — product domain (foundation)

Product domain (`kind => product`, `locked => true`) for **content creator aggregation** (display name **vTual**). Foundation ships creator/management/channel/content schema and CRUD skeleton **without** live YouTube/Twitch/Kick ingestion.

| | |
|--|--|
| Code | `app/Domain/Vtual/` |
| Config | [`config/products.php`](../../../config/products.php) key `Vtual` |
| Registry | [`config/domains.php`](../../../config/domains.php) `Vtual` product locked |
| API | `routes/api/v1/vtual.php` |
| Web | `routes/web/vtual.php` |
| Seeder | `VtualRoleSeeder` (after core `RoleSeeder`; also seeds platforms) |
| Product gate | `product.active:Vtual` middleware (see [Product Activations](../product-activations/README.md)) |
| Menu auto-hide | `vtual.index` via `menu_routes`; hidden when activation not `active` |
| Legacy audit | [archive/vtual-legacy-audit.md](../../archive/vtual-legacy-audit.md) |

## Concepts

| Concept | Behavior |
|---------|----------|
| Creator | Public profile separate from Identity `User` (`vtual_creators`). Soft delete + `status` active/archived. |
| Creator membership | `vtual_creator_members`: one user may own/edit/view **many** creators (`owner` \| `editor` \| `viewer`). Create always inserts actor as `owner`. |
| Management | Talent org (`vtual_managements`), not core Spatie role `Management`. |
| Management staff | `vtual_management_members` (`owner` \| `admin` \| `staff`). |
| Creator assignment | `vtual_management_creators` with `joined_at` / `left_at` (historical leave without hard delete). |
| Platform | Seeded catalog: youtube, twitch, **kick** (kick `enabled = false`). |
| Channel | External channel on a creator; unique `(platform_id, external_id)`; status starts `pending`. |
| Content | Normalized shell table (`vtual_contents`) for live/archive/vod — **no sync workers in foundation**. |
| External links | Morph `vtual_external_links` for creator/management social/donation URLs (schema ready; minimal API in foundation). |

### Identity model (locked product decision)

```text
User (Identity) 1 --- N CreatorMember --- N Creator
Management 1 --- N ManagementMember --- N User
Management 1 --- N ManagementCreator --- N Creator  (left_at nullable)
Creator 1 --- N Channel --- N Content
```

Core role name **Management** (platform H-RBAC ladder) is **unrelated** to `vtual_managements`.

## Activation

```php
// config/products.php
'Vtual' => [
    'activation_type' => 'implicit',
    'fulfillment_target' => null,
    'menu_routes' => ['vtual.index'],
],
```

- Register hook activates all implicit products (including Vtual).
- `product.active:Vtual` lazy-creates missing implicit rows on first API hit.
- No paid Service SKU yet (`fulfillment_target` null).

## Authorization

Middleware stack:

```text
auth:api → product.active:Vtual → permission:<perm> → Policy → service resource checks
```

### Permissions (`domain_kind = product`)

Own (granted to core `User` + product roles):

```text
view vtual creator | create vtual creator | edit vtual creator | delete vtual creator
view vtual management | create vtual management | edit vtual management
view vtual channel | create vtual channel
```

Any (internal core roles Root/Admin/Moderator/Management + product Admin):

```text
view any vtual resource
manage any vtual resource
```

Product roles (levels 2100–2300): `Vtual Admin`, `Vtual Editor`, `Vtual Viewer`.

Resource scope:

- Creator view/mutate via membership role or `* any vtual resource`.
- Management assign creators requires management assign role **and** creator mutate on that creator.
- Channel ops scoped through parent creator access.

## API (`/api/v1/vtual/*`)

| Method | Path | Permission | Purpose |
|--------|------|------------|---------|
| GET | `/platforms` | view vtual creator | Platform catalog (includes disabled Kick) |
| GET/POST | `/creators` | view / create | List (scoped) / create (actor becomes owner) |
| GET/PATCH/DELETE | `/creators/{id}` | view / edit / delete | Show / update / archive |
| GET/POST | `/creators/{id}/channels` | view channel / edit creator | List / register channel |
| GET | `/channels/{id}` | view channel | Show channel |
| GET/POST | `/managements` | view / create | List / create management |
| GET/PATCH | `/managements/{id}` | view / edit | Show / update |
| POST | `/managements/{id}/creators` | edit management | Assign creator |
| DELETE | `/managements/{id}/creators/{creatorId}` | edit management | Unassign (`left_at`) |

## Web Blade

| Route | View |
|-------|------|
| `GET vtual` (`vtual.index`) | Creators + managements lists + create forms + platform badges |
| `GET vtual/creators/{id}` | Creator detail + channel register form |
| Mutations | POST forward via `ConsumesApi` to versioned API |

Layout: `resources/views/{WEB_LAYOUT}/vtual/` (default `adminlte4`).

## Seeders

1. `VtualRoleSeeder` — product roles/permissions + `PlatformService::seedDefaults()`.
2. `MenuSeeder` — Products → **vTual** leaf (`vtual.index`).
3. `DatabaseSeeder` calls `VtualRoleSeeder` after `FileHostingRoleSeeder`.

## Kick strategy (foundation)

- Platform row exists with `enabled = false`.
- `ChannelService::create` rejects disabled platforms (no scrape path).
- Re-enable only after official API decision (see archive audit).

## Known limitations (deferred)

| Item | Status |
|------|--------|
| YouTube WebSub / Data API adapters | Not implemented |
| Twitch EventSub / Helix live poll | Not implemented |
| Kick integration | Deferred; disabled seed |
| Content lifecycle sync + CCV snapshots | Table only |
| Multi-stream session correlation | Not implemented |
| Public homepage / SEO creator & management profiles | Not implemented |
| Paid Service catalog SKUs for Vtual | Not implemented |
| Data migration from broadercastv2 | Not planned in foundation |

## Tests

```bash
composer test -- --filter=Vtual
```

| Suite | Coverage |
|-------|----------|
| `SlugGeneratorTest` | Slugify + unique suffix |
| `CreatorMemberRoleTest` | Mutate / manage members matrix |
| `CreatorServiceTest` | Multi-creator ownership, slug collision, archive |
| `ManagementServiceTest` | Owner membership, assign/unassign `left_at` |
| `ChannelServiceTest` | Pending channel, Kick disabled, duplicate external id |
| `VtualApiTest` | CRUD API, product gate, lazy activation, peer isolation |
| `RegisterVtualActivationTest` | Register creates implicit Vtual activation |

## Related

- [product-activations/README.md](../product-activations/README.md) — runtime gate
- [file-hosting/README.md](../file-hosting/README.md) — sibling product domain pattern
- [identity/README.md](../identity/README.md) — User hub, product RBAC `domain_kind`
- [platform/architecture.md](../../platform/architecture.md) — domain registry
- [archive/vtual-legacy-audit.md](../../archive/vtual-legacy-audit.md) — broadercast claim ledger
