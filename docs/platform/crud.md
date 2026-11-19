# CRUD architecture & repository pattern

Layering for simple API CRUD and the rule for when the shared `EloquentCrudRepository` is enough.

A standard five-action resource routes through `Route::apiResource(...)->except(['create', 'edit'])` and flows `HTTP → Form Request → Controller → Service → Repository → Model`. The Form Request validates and authorizes, the Service owns business rules (failures throw `BusinessRuleException` subclasses), the Controller is thin and returns the canonical envelope via `ApiResponse`, the Repository only queries and persists, and the Model only maps. Use the shared generic repository for straightforward CRUD with the rules in a Service; reach for a dedicated repository interface when queries get complex, a write spans tables, or the domain needs named persistence methods — never extend the generic repository with domain methods. Human-readable display fields (`*_formatted`, `*_human`) are produced in Eloquent Resources by the shared presenters, never in controllers, services, or models.

| | |
|---|---|
| Code | `packages/platform/src/Repositories/Eloquent/EloquentCrudRepository.php`, `App\Domain\{Domain}\{Services,Repositories,Models}`, `App\Domain\{Domain}\Http\{Controllers,Requests,Resources}` |
| Config | — |
| API | `routes/api/v1/{slug}.php` |
| SPA | consumes `*_formatted` / `*_human` only — no view-layer reformatting |
| Tests | `tests/Domain/{Domain}/`, `tests/Unit/Support/Presentation/*` |
| Pakem | [crud.mdc](../../.cursor/rules/crud.mdc) · [api-resources.mdc](../../.cursor/rules/api-resources.mdc) |
| Deep dive | [reference-crud.md](reference-crud.md) |
