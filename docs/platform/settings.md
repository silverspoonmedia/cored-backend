# Runtime settings (Web surface)

Operational configuration that changes application behavior **without** redeploying secrets or infra profiles. Secrets and infrastructure bindings stay in `.env`.

Values live in `system_settings` and are edited at **Administration → Settings**, a single SPA hub that renders one group per surface (`platform`, `archivd`, `microstock`) plus notification audiences. Runtime reads go through `App\Support\Settings\SettingsReader` against a forever-cached snapshot; a key with no DB row yet falls back to the owning `config/*.php` **PHP literal** default, then to the caller default. Never `config()->set()` or `env()` outside config files. Field labels resolve dynamically from `SettingRegistry` keys, so every registry key must exist in both locale files.

| | |
|---|---|
| Code | `vendor/silverspoonmedia/platform/src/Support/Settings/**`, `app/Domain/*/Services/*Settings*`, `config/*.php` |
| Config | `config/settings-local.php` (local dev overrides) |
| API | `GET`/`PUT /api/v1/admin/settings/{group}` |
| SPA | `SettingsFormView` (`/apps/settings/platform`, `/apps/archivd/settings`, `/apps/microstock/settings`) |
| Commands | `settings:rebuild-cache`, `config:verify-secrets` |
| Tests | `SettingRegistryTranslationTest`, `SettingsServiceTest` |
| Pakem | [settings.mdc](../../.cursor/rules/settings.mdc) · [environment.mdc](../../.cursor/rules/environment.mdc) |
| Deep dive | [reference-settings.md](reference-settings.md) |
