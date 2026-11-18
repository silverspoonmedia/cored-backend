# Ai — central AI transport

Core domain that answers *where* we call AI, *with which key*, and *with which timeout and retry*. It owns the connection and never the price — token rates and the model catalog stay in [inference](../inference/README.md). It exists because Microstock (vision + thinking) and other consumers all speak the same `openai_compatible` chat-completions shape, and per-consumer env keys made a key rotation a five-file redeploy. Code: `packages/ai/src/Ai/` (package `silverspoonmedia/ai`, bundled with Inference). Invariants: [.cursor/rules/ai.mdc](../../../.cursor/rules/ai.mdc).

| Need | Path |
|------|------|
| Code | `packages/ai/src/Ai/` — `AiConnectionService`, `AiConnectionResolver`, `AiConnectionSecrets` |
| Config | [`packages/ai/config/ai.php`](../../../packages/ai/config/ai.php) (non-secret defaults only) |
| Storage | `ai_connections` — Tier C, envelope-encrypted (`resource_type = ai_connections`) |
| API | [`packages/ai/routes/ai.php`](../../../packages/ai/routes/ai.php) — `/api/v1/ai/connections` |
| SPA | Administration → **AI connections** (`ai.connections.*`), permissions `view`/`manage ai connections` |
| Local seed | `packages/ai/src/Ai/Database/Seeders/AiConnectionSeeder.php` + gitignored `database/seeders/data/ai-connections.local.php` |
| Realtime | `ai.connections` private channel — `.ai.connection.changed` |
| Commands | `php artisan ai:import-legacy-env` · `php scripts/ai/list_models.php` · `php scripts/ai/probe_models.php` |
| Tests | `packages/ai/tests/Ai/` (`composer test -- --filter=Ai`) · `tests/Concerns/BindsAiConnection.php` |
| Pakem | [.cursor/rules/ai.mdc](../../../.cursor/rules/ai.mdc) |
| Deep dive | [reference.md](reference.md) — schema, test gate, resolution, import, local seeding |
| Related | [inference](../inference/README.md) (pricing) · [platform/configuration-policy.md](../../platform/configuration-policy.md) (tiers) · [microstock](../microstock/README.md) (consumer) |
