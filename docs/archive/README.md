# Archive

Historical assessments, one-shot audits, and migration handoffs. **Not** for daily learning — use [docs/README.md](../README.md) living guides.

Some archive files describe a former three-repo workspace (Monolith + Gateway + Frontend). That workflow is dissolved; treat those sections as historical only.

The **Status** column exists because four entries are not purely historical: their *findings* are a snapshot, but a procedure, a design rationale or an open item inside them is still the current reference. Those four are named in the column, so a reader knows which ones a living doc deliberately delegates to. Everything else is frozen narrative — do not update it, do not cite it as current behavior.

| File | What it was | Status |
|------|-------------|--------|
| [internal-monorepo-assessment.md](internal-monorepo-assessment.md) | Pre/post logical monorepo assessment | Historical |
| [internal-monorepo-porting.md](internal-monorepo-porting.md) | Porting checklist / gotchas | Historical |
| [bootstrap-task-2026-08.md](bootstrap-task-2026-08.md) | Original vTual bootstrap brief; the source of the `bootstrap-task-2026-08.md:<line>` references still cited in code comments | Historical, cited by line from code |
| [security-audit-2026-06-16.md](security-audit-2026-06-16.md) | Blade-era OWASP audit snapshot (living posture in [platform/security.md](../platform/security.md)) | Historical — all 20 findings resolved; the 2026-09-27 addendum re-verifies FINDING-007 (pinned) and FINDING-008 (was stale: 18 transitive advisories found and fixed) |
| [auth-activity-assessment.md](auth-activity-assessment.md) | Auth event → activity log matrix (living summary in [platform/security.md](../platform/security.md)) | Historical |
| [crypto-activity-assessment.md](crypto-activity-assessment.md) | Envelope decrypt audit deep dive (living: [platform/envelope-encryption.md](../platform/envelope-encryption.md)) | Historical |
| [spa-consumer-decision-findings.md](spa-consumer-decision-findings.md) | Non-blocking SPA decisions that are not API business rules (living contract: [integration/reference-vue-workers-spa.md](../integration/reference-vue-workers-spa.md)) | **Partly live** — the decisions are still binding guidance |
| [ui-patterns-blade.md](ui-patterns-blade.md) | Archived Blade + AdminLTE 4 UI patterns; every path in it is deleted | Historical |
| [AUDIT-LOG.md](AUDIT-LOG.md) | 2026-06-27 docs vs code audit notes | Historical |
| [vtual-legacy-audit.md](vtual-legacy-audit.md) | broadercastv2 claim ledger + gap analysis before Vtual product domain | Historical |
| [vtual-vue-island-assessment.md](vtual-vue-island-assessment.md) | Vue island promotion analysis (2026-08); all surfaces are SPA now | Historical |
| [vtual-youtube-stability-assessment-2026-08-07.md](vtual-youtube-stability-assessment-2026-08-07.md) | YouTube ops probe rubric (credentials, gateway, DB, quota, scheduler) | **Partly live** — the rubric is re-runnable; re-run it after major ingestion changes |
| [microstock-probes/](microstock-probes/) | One-shot AI probe summaries (2026-07/08); living summary in [domains/microstock/README.md](../domains/microstock/README.md) | **Partly live** — `decoupled_stages_collection_summary.md` justifies the shipped `url` default in [`config/microstock.php`](../../config/microstock.php) |

Living architecture: [platform/architecture.md](../platform/architecture.md).
