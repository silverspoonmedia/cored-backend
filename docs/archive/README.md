# Archive

Historical assessments, one-shot audits, and migration handoffs. **Not** for daily learning — use [docs/README.md](../README.md) living guides.

Some archive files describe a former three-repo workspace (Monolith + Gateway + Frontend). That workflow is dissolved; treat those sections as historical only.

The **Status** column exists because several entries are not purely historical: their *findings* are a snapshot, but a procedure, a design rationale or an open item inside them is still the current reference. Those are named in the column, so a reader knows which ones a living doc deliberately delegates to. Everything else is frozen narrative — do not update it, do not cite it as current behavior.

| File | What it was | Status |
|------|-------------|--------|
| [internal-monorepo-assessment.md](internal-monorepo-assessment.md) | Pre/post logical monorepo assessment | Historical |
| [core-domain-packages-poc.md](core-domain-packages-poc.md) | Navigation + Notification extracted to Composer path packages (2026-11-16): what was proven, what was decided, what was left in `app/` | **Live reference** — the reasoning behind [integration/core-packages.md](../integration/core-packages.md) |
| [core-packages-bundling-decision.md](core-packages-bundling-decision.md) | ActivityLog + ErrorLog bundled into `silverspoon/audit` (2026-11-17): why one domain ≠ one package, the four kernel contracts it needed | **Live reference** — the bundle rule behind [integration/core-packages.md](../integration/core-packages.md) |
| [billing-package-decision.md](billing-package-decision.md) | PaymentGateway + Service bundled into `silverspoon/billing` (2026-11-17): one money surface, and the five kernel seams that replaced its imports of product domains | **Live reference** — why billing is one package and how a packaged domain fulfills into products |
| [ai-package-decision.md](ai-package-decision.md) | Inference + Ai bundled into `silverspoon/ai` (2026-11-17): a rate needs a reachable endpoint, and the three kernel seams (ledger, consolidator registry, usage resource config) that replaced Inference's imports of Microstock | **Live reference** — why AI is one package and how a packaged catalog reads a product's ledger |
| [internal-monorepo-porting.md](internal-monorepo-porting.md) | Porting checklist / gotchas | Historical |
| [security-audit-2026-06-16.md](security-audit-2026-06-16.md) | Blade-era OWASP audit snapshot (living posture in [platform/security.md](../platform/security.md)) | Historical — all 20 findings resolved; the 2026-09-27 addendum re-verifies FINDING-007 (pinned) and FINDING-008 (was stale: 18 transitive advisories found and fixed) |
| [auth-activity-assessment.md](auth-activity-assessment.md) | Auth event → activity log matrix (living summary in [platform/security.md](../platform/security.md)) | Historical |
| [crypto-activity-assessment.md](crypto-activity-assessment.md) | Envelope decrypt audit deep dive (living: [platform/envelope-encryption.md](../platform/envelope-encryption.md)) | Historical |
| [spa-consumer-decision-findings.md](spa-consumer-decision-findings.md) | Non-blocking SPA decisions that are not API business rules (living contract: [integration/reference-vue-workers-spa.md](../integration/reference-vue-workers-spa.md)) | **Partly live** — the decisions are still binding guidance |
| [ui-patterns-blade.md](ui-patterns-blade.md) | Archived Blade + AdminLTE 4 UI patterns; every path in it is deleted | Historical |
| [AUDIT-LOG.md](AUDIT-LOG.md) | 2026-06-27 docs vs code audit notes | Historical |
| [blog-sunset-postmortem.md](blog-sunset-postmortem.md) | Why the Blog product domain was removed (2026-10-02), what left, and the restore point | **Live reference** — the restore recipe and tag links are current |
| [vtual-sunset-postmortem.md](vtual-sunset-postmortem.md) | Why the vTual product domain was removed (2026-10-07), what left, and the restore point | **Live reference** — the restore recipe and tag links are current |
| [microstock-probes/](microstock-probes/) | One-shot AI probe summaries (2026-07/08); living summary in [domains/microstock/README.md](../domains/microstock/README.md) | **Partly live** — `decoupled_stages_collection_summary.md` justifies the shipped `url` default in [`config/microstock.php`](../../config/microstock.php) |

Living architecture: [platform/architecture.md](../platform/architecture.md).
