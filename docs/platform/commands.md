# Artisan commands (project-owned index)

Map of the **project-specific** command surface, beyond Laravel and package defaults.

Commands live in `app/Console/Commands/` and `app/Domain/*/Console/Commands/`; recurring runs are declared in `routes/console.php`. This page is the map — the owning domain doc keeps the behaviour detail, and the full per-group index with links is in [reference-commands.md](reference-commands.md). `php artisan schedule:list` is the live schedule; production needs `schedule:work` (or cron) plus one `queue:work` per queue.

| | |
|---|---|
| Code | `app/Console/Commands/**`, `app/Domain/*/Console/Commands/**`, `routes/console.php` |
| Config | `config/*.php` (per-domain command knobs) |
| Commands | `php artisan list`, `php artisan schedule:list` |
| Tests | `tests/Unit/Documentation/CoreScriptTest.php`, per-command feature tests under `tests/Feature/Console/` |
| Pakem | [environment.mdc](../../.cursor/rules/environment.mdc) · [database-safety.mdc](../../.cursor/rules/database-safety.mdc) |
| Deep dive | [reference-commands.md](reference-commands.md) |
