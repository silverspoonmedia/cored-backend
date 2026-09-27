# Configuration & secrets policy

Canonical rules for **where a value may live** and how runtime settings are read. Three tiers, never mixed.

Tier **A** is `.env` plus `config/*.php` `env()` bindings (app crypto, JWT signing, DB/Redis, OCI, mail/broadcast, storage and payment secrets) and is read through `config()` at bootstrap only. Tier **B** is `system_settings`, edited in the Settings UI (JWT TTLs, upload limits, notification delivery, claim TTLs, timezone knobs, AI consumer knobs) and read through `setting()` / `setting_config()` against a forever cache. Tier **C** is a dedicated envelope-encrypted credential table (AI connections, vTual platform credentials, Microstock marketplace credentials) read through the model cast. Putting a Tier B value in `.env`, or a Tier C secret in `.env` or `system_settings`, is a defect — not a shortcut.

| | |
|---|---|
| Code | `app/Support/Settings/**`, `app/Support/Encryption/**`, `config/*.php` |
| Config | `config/settings-local.php` (local dev overrides) |
| Commands | `settings:rebuild-cache`, `env:curate`, `ai:import-legacy-env` |
| Tests | `SettingRegistryTranslationTest`, `EnvProfileSyncTest` |
| Pakem | [environment.mdc](../../.cursor/rules/environment.mdc) · [settings.mdc](../../.cursor/rules/settings.mdc) |
| Deep dive | [reference-configuration-policy.md](reference-configuration-policy.md) |
