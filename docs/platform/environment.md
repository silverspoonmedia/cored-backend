# Environment configuration

Laravel loads **one** `.env` at boot; Monolith adds profile snapshots so one machine can switch between local, staging, and production without losing per-profile values.

`.env.example` is the master catalog, `.env.{local,staging,production}.example` are the committed profile templates, and `.env` is the gitignored active file Laravel actually reads. The master, all three templates, and `EnvironmentVariableCatalog::types()` must expose the **same set of live `KEY=` assignments** — a commented `# KEY=` line does not count. `env:install` copies a template and generates `APP_KEY`, `JWT_SECRET`, and `ENCRYPTION_BLIND_INDEX_PEPPER`; `env:switch` swaps profiles through a transactional backup; `env:curate` reformats the active `.env` to the template layout with typed quoting. Secrets and infra bindings live here (Tier A); operational knobs live in the DB (Tier B) — see [configuration-policy.md](configuration-policy.md). PHP 8.4 is required, not 8.3.

| | |
|---|---|
| Code | `packages/platform/src/Support/Environment*.php`, `app/Console/Commands/*Env*.php` |
| Config | `.env.example`, `.env.{local,staging,production}.example`, `config/*.php` |
| Commands | `env:install`, `env:switch`, `env:curate`, `env:backup:create`, `env:restore`, `key:generate`, `config:rebuild`, `config:verify-secrets` |
| Tests | `tests/Feature/Console/EnvProfileSyncTest.php`, `EnvironmentTemplateQuotingTest` |
| Pakem | [environment.mdc](../../.cursor/rules/environment.mdc) |
| Deep dive | [reference-environment.md](reference-environment.md) |
