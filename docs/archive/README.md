# Archive

Historical assessments, one-shot audits, and migration handoffs. **Not** for daily learning — use [docs/README.md](../README.md) living guides.

Some archive files describe a former three-repo workspace (Monolith + Gateway + Frontend). That workflow is dissolved; treat those sections as historical only.

| File | What it was |
|------|-------------|
| [internal-monorepo-assessment.md](internal-monorepo-assessment.md) | Pre/post logical monorepo assessment |
| [internal-monorepo-porting.md](internal-monorepo-porting.md) | Porting checklist / gotchas |
| [bootstrap-task-2026-08.md](bootstrap-task-2026-08.md) | Original vTual bootstrap brief; the source of the `TASK.md:<line>` references still cited in code comments |
| [security-audit-2026-06-16.md](security-audit-2026-06-16.md) | Blade-era OWASP audit snapshot (living posture in [platform/security.md](../platform/security.md)) |
| [auth-activity-assessment.md](auth-activity-assessment.md) | Auth event → activity log matrix (living summary in [platform/security.md](../platform/security.md)) |
| [crypto-activity-assessment.md](crypto-activity-assessment.md) | Envelope decrypt audit deep dive (living: [platform/envelope-encryption.md](../platform/envelope-encryption.md)) |
| [spa-consumer-decision-findings.md](spa-consumer-decision-findings.md) | Non-blocking SPA decisions that are not API business rules (living contract: [integration/reference-vue-workers-spa.md](../integration/reference-vue-workers-spa.md)) |
| [ui-patterns-blade.md](ui-patterns-blade.md) | Archived Blade + AdminLTE 4 UI patterns; every path in it is deleted |
| [AUDIT-LOG.md](AUDIT-LOG.md) | 2026-06-27 docs vs code audit notes |
| [vtual-legacy-audit.md](vtual-legacy-audit.md) | broadercastv2 claim ledger + gap analysis before Vtual product domain |
| [vtual-vue-island-assessment.md](vtual-vue-island-assessment.md) | Vue island promotion analysis (2026-08); all surfaces are SPA now |
| [vtual-youtube-stability-assessment-2026-08-07.md](vtual-youtube-stability-assessment-2026-08-07.md) | YouTube ops probe rubric (credentials, gateway, DB, quota, scheduler) |
| [microstock-probes/](microstock-probes/) | One-shot AI probe summaries (2026-07/08); living summary in [domains/microstock/README.md](../domains/microstock/README.md) |

Living architecture: [platform/architecture.md](../platform/architecture.md).
