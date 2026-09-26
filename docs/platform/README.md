# Platform docs — sub-index

Kernel and cross-cutting topics. Each entry is a brief; the deep dive holds the tables, inventories, and procedure detail. Env/DB/secret boundaries start at [configuration-policy.md](configuration-policy.md).

## Structure & delivery

| Brief | Topic | Deep dive |
|-------|-------|-----------|
| [architecture.md](architecture.md) | Domain modules, `kind`/`locked`, routes, namespaces | [reference-architecture.md](reference-architecture.md) |
| [api-routing.md](api-routing.md) | Global vs versioned route layers | [reference-api-routing.md](reference-api-routing.md) |
| [crud.md](crud.md) | Layered CRUD, shared repository, dual-field resources | [reference-crud.md](reference-crud.md) |
| [commands.md](commands.md) | Project-owned Artisan command index | [reference-commands.md](reference-commands.md) |
| [api-envelope.md](api-envelope.md) | JSON envelope + error mapping | — |
| [api-aggregation.md](api-aggregation.md) | Standalone-first, `/batch` fan-in | — |
| [list-display-modes.md](list-display-modes.md) | `display_mode=query`, `actions[]`, lookups | — |
| [identifiers.md](identifiers.md) | UUID v7 primary keys | — |

## Configuration & operations

| Brief | Topic | Deep dive |
|-------|-------|-----------|
| [environment.md](environment.md) | `.env` profiles, typed quoting, `env:curate` | [reference-environment.md](reference-environment.md) |
| [configuration-policy.md](configuration-policy.md) | Tier A/B/C, where a value may live | [reference-configuration-policy.md](reference-configuration-policy.md) |
| [settings.md](settings.md) | `system_settings` registry, Settings UI | [reference-settings.md](reference-settings.md) |
| [cache-policy.md](cache-policy.md) | Forever cache + explicit invalidation | — |
| [octane.md](octane.md) | Long-lived worker safety | — |
| [database-safety.md](database-safety.md) | Dev-DB wipe prevention, migration portability | — |
| [tooling.md](tooling.md) | Pint wrapper, CI workflow | — |

## Security & language

| Brief | Topic | Deep dive |
|-------|-------|-----------|
| [security.md](security.md) | OWASP audit, CORS perimeter, response minimization | [reference-security.md](reference-security.md) |
| [envelope-encryption.md](envelope-encryption.md) | Tiered crypto, OCI KMS, decrypt cache | [reference-envelope-encryption.md](reference-envelope-encryption.md) |
| [localization.md](localization.md) | Locales, resolution, parity guards, curation | [reference-localization.md](reference-localization.md) |
| [naming-conventions.md](naming-conventions.md) | Settled terms per locale + drift logs (glossary) | — |

## Related

- [docs/README.md](../README.md) — full documentation index
- [AGENTS.md](../../AGENTS.md) — always-on rule map
