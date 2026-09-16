# Blog — posts, categories, tags, media

Agent entry first: root [AGENTS.md](../../../AGENTS.md). Core domain (kind=core). Code: `app/Domain/Blog/`.

## Scope

WordPress-like writing domain for the SPA admin: WYSIWYG posts (Tiptap in the SPA, AdminLTE4 `/apps/blog/*`), categories, tags, featured thumbnails, and inline editor images. **No public reader pages yet** — admin CRUD only.

## API (`/api/v1/blog/*`)

| Group | Endpoints | Middleware |
|-------|-----------|------------|
| `blog/posts` | `GET /` (query list), `GET statuses`, `GET trashed`, `GET {post}`, `POST /`, `PUT/PATCH {post}`, `DELETE {post}`, `POST {post}/restore` | `auth:api`; `view posts` for reads, `manage posts` for writes, `restore posts` for restore |
| `blog/categories` | standard index/show/store/update/destroy | `view posts` / `manage posts` |
| `blog/tags` | standard index/show/store/update/destroy | `view posts` / `manage posts` |
| `blog/media` | `POST /` multipart image upload, `DELETE {media}` | `manage posts` |

Permissions come from `RoleSeeder` (core): `view posts`, `manage posts`, `restore posts`.

## Schema (`blog_posts`, `blog_categories`, `blog_tags`, `blog_media`, pivots)

UUID keys. `blog_posts.status` is a `PostStatus` enum (`draft` / `published`); `published_at` is set automatically on first publish. `featured_media_id` → `blog_media` (nullable). Soft deletes on posts only.

## Content sanitization (mandatory)

`PostService` runs `content` through HTMLPurifier (`mews/purifier`, profile `blog` in `config/purifier.php`) **before storage** — the whitelist there is the single source of truth, because the SPA renders content with `v-html`. Extend the profile and the SPA editor toolbar together.

Slugs: auto-generated from `title` when omitted, uniqueness enforced against active **and** trashed rows (`-2`, `-3`, … suffix).

## Media

`config/blog.php`: `media.disk` (default `public`, env `BLOG_MEDIA_DISK` — set to `s3ArchivdCe` in production/local profiles so URLs come from the public CE bucket without the `public/storage` symlink), `media.directory` (`blog`), `media.max_size_kb` (4096). Allowed mimes: jpg, jpeg, png, gif, webp. `BlogMedia::url()` resolves the public URL via the storage disk (each row pins its own `disk`). Media rows are not garbage-collected when posts are deleted — deletion is explicit.

## Menu integration

Sidebar: Workspace header › **Blog** parent (route `blog.posts.index`) with Posts / Categories / Tags children; roles Root/Admin/Moderator/Management. Route names registered in `config/spa-routes.php` — including `blog.posts.trashed`, reached from the Posts view rather than a menu child. `MenuSeeder` + `MenuSeederTest` pin the tree; `MenuSeederConformanceTest` pins validation conformance.

## Row actions (query list)

`PostQueryRowActions` attaches per-row `actions[]`: `edit` (nav → `blog.posts.edit`) and `delete` (mutate `DELETE blog/posts/{id}`, gated `manage posts`, confirmation copy `lang/*/list_actions.php` → `delete_post`). `PostTrashedQueryRowActions` attaches `restore` (mutate `POST blog/posts/{id}/restore`, gated `restore posts`). Pinned by `BlogPostApiTest`. Contract: [list-display-modes.md](../platform/list-display-modes.md).

## SPA surface

Views in `monolith-vue-workers/src/views/blog/`, composables `src/composables/blog/` (incl. `useBlogMedia` multipart upload), editor `src/components/blog/RichTextEditor.vue` (Tiptap, headless) and `src/components/blog/FeaturedImagePicker.vue` (takes the full `BlogMedia` object so existing posts preview the stored URL, not the UUID). Contract details: [vue-workers-spa.md](../integration/vue-workers-spa.md).
