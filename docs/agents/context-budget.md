# Agent context budget

How to keep Cursor token use low without splitting the Laravel app into microservices. Always-on index: root [AGENTS.md](../../AGENTS.md). Rules still apply; **do not load the owning docs until the matching rule is in play**.

## What is expensive

A dual-root workspace injects **both** repos’ `AGENTS.md` on every chat. Large locale JSON, `docs/integration/vue-workers-spa.md` (historical god-file), domain READMEs, and `storage/logs` used to dominate retrieval. Ignore files (`.cursorignore`, `.cursorindexingignore`) keep logs/vendor/`node_modules` and locale dumps out of indexing; Grep / targeted `Read` still work when you need a key.

## Which workspace to open

| File | When |
|------|------|
| [Monolith-Backend.code-workspace](../../Monolith-Backend.code-workspace) | Default for API, migrations, seeders, PHP tests. Tag `[backend]`. |
| Sibling [Monolith-SPA.code-workspace](../../../monolith-vue-workers/Monolith-SPA.code-workspace) | Default for Vue screens, SPA i18n, Worker shell. Tag `[frontend]`. |
| [Monolith-Fullstack.code-workspace](../../Monolith-Fullstack.code-workspace) | Only when the **contract** moves: endpoints, Resource shapes, cookie/CSRF/headers, `config/spa-routes.php` ↔ Vue Router names. |

Do not `@AGENTS.md`, `@folder`, or `@src/i18n/locales` in the prompt. Name specific paths. One feature = one new chat.

## What to open per task

1. The file you will edit + its nearest test.
2. The **one** domain/platform guide for that area ([docs/README.md](../README.md)), or the **Agent entry** at the top of a long domain README — not the whole README.
3. [vue-workers-spa.md](../integration/vue-workers-spa.md) **only** for contract changes. SPA behavior: sibling topic docs (`auth-session.md`, `api-client.md`, `i18n.md`, `ui-patterns.md`, `routes.md`).
4. Locale files: Grep the key, patch all seven locales, run the parity gate. Never `Read` an entire `lang/*.json` or SPA locale JSON.

Glob rules under `.cursor/rules/` attach navigation, Archivd, i18n, and env essays only when matching files are in play.

## Targets

| Task kind | Aim (prompt + retrieval, not tool schema) |
|-----------|-------------------------------------------|
| `[backend]` or `[frontend]` narrow | median well under 70k tokens |
| Fullstack contract | under 120k |

## Explicitly out of scope

Do not rewrite the monolith as microservices to save editor tokens. Domain folders already are the module boundary. Splitting locale JSON per namespace and breaking god-views (`ArchivdView.vue`, `src/api/types.ts`) is **P2** — only if these targets stay missed after the ignore files, slim `AGENTS.md`, glob rules, and contract doc are in daily use.
