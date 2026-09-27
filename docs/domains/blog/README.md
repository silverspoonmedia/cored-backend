# Blog — posts, categories, tags, media

Admin-only writing domain for the SPA: WYSIWYG posts (Tiptap in the SPA), categories, tags, featured thumbnails, and inline editor images. Core domain (`kind = core`), code under `app/Domain/Blog/`. There is **no public reader surface** — every endpoint is `auth:api` and permission-gated, and the SPA is the only consumer. Normative invariants (sanitization whitelist, slug uniqueness, soft-delete scope, media disk pinning, permissions, row actions) live in the owning pakem, not here.

| Need | Open |
|------|------|
| Code | `app/Domain/Blog/` — `Models/`, `Services/`, `Http/Controllers/API/V1/`, `Http/Requests/`, `Http/Resources/`, `Enums/PostStatus.php` |
| Config | `config/blog.php` (media disk/directory/max size), `config/purifier.php` profile `blog` |
| API | `/api/v1/blog/posts` · `/blog/categories` · `/blog/tags` · `/blog/media` — [routes/api/v1/blog.php](../../../routes/api/v1/blog.php) |
| SPA | `monolith-vue-workers/src/views/blog/`, `src/composables/blog/` (incl. `useBlogMedia` multipart upload), `src/components/blog/RichTextEditor.vue` (Tiptap), `FeaturedImagePicker.vue` |
| Menu / routes | `MenuSeeder` (Blog parent → Posts / Categories / Tags), `config/spa-routes.php` |
| Tests | `tests/Domain/Blog/Feature/Http/Controllers/API/V1/BlogPostApiTest.php`; menu coverage in `MenuSeederTest` + `MenuSeederConformanceTest` |
| Pakem | [.cursor/rules/blog.mdc](../../../.cursor/rules/blog.mdc) — read this before touching the domain |
| Contract | [vue-workers-spa.md](../../integration/vue-workers-spa.md), [list-display-modes.md](../../platform/list-display-modes.md) |
