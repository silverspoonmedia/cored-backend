# AI Agents — Monolith

**Role:** Laravel 13 application — domain logic, database, JWT/RBAC, versioned API (`/api/v1/*`), and Blade admin.

## Agent protocol (mandatory)

Three instructions below are canonical here — no other document may re-define them.

1. **Assess before changing.** Before any change, check, validate, and assess the task: read the relevant code and docs until the work context is fully understood. No blind edits.
2. **Update docs with every task.** After finishing a task, always update the relevant related documentation so code and docs never drift (details: rule 2 in [docs/agents/core-rules.md](docs/agents/core-rules.md)).
3. **`wrap up the docs` keyword.** The exact phrase `wrap up the docs` triggers a session-wide consolidation: reconcile ALL implementations from the session against the documentation as a whole. Per-task doc updates (point 2) cover only that task; the final state may differ — this keyword closes that gap.

## Read first

**Core rules (mandatory, read every task):** [docs/agents/core-rules.md](docs/agents/core-rules.md) — full-stack requirement, living docs, database safety, Octane readiness, localization. Tag a task `[backend]` / `[frontend]` / `[fullstack]` (default) to scope the full-stack requirement — see rule 1.

**Doc map (load domain/platform guides on demand):** [docs/README.md](docs/README.md)

Daily work stays in **this repository only**.