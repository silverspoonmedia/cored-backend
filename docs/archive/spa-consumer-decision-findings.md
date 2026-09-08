# SPA consumer decision findings (archived)

Snapshot of non-blocking SPA (or former Blade-JS) decisions that are **not** API business rules. Living contract: [integration/vue-workers-spa.md](../integration/vue-workers-spa.md#mimic-doctrine). Do not “fix” these as part of a contract change.

Blocking cases (client `canAffordAnalysis` math, sync-status default `'midtrans'`, hardcoded purchase qty `99`) were moved to the API.

| Finding | Why it is decision-making | Why not blocking |
|---------|---------------------------|------------------|
| Accept Anyway always painted on the claim page | SPA cannot read `vtual.claim.accept_anyway_enabled` | API 422s when off |
| Discord verify settings on the creator claim page are not read | SPA cannot read `vtual.claim` Discord-verify flags | Button can still 422; bio verify still works |
| `collection_status` → CTA map | Local state machine in `ArchivdView` | API rejects illegal transitions |
| Invoice/purchase `status` → PaymentSteps / hero | Chrome | Display only |
| `plan_kind === 'addon'` qty editor | Chrome from a field | API rejects non-addon qty |
| JWT `hasPermission` hide-buttons | Stale until refresh | API still 403s |
| `analysisEstimateUsd` display multiply | Preview math in `ArCollectionDescribeModal` | Does not gate analyze |
| Describe chips + min-10 | Port of Blade `collection-describe.js` | Mimic, not new money math |
| `useMicrostock.createWorkspace` invents `is_owner` / `Owner` | Optimistic shape vs resource | Local list until refetch |
| `ROLE_RANK`, kind icons, chip catalogs, settings slug list, invite roles, subscription cycle literals | Presentation / form enums | API validates |
| `projectedStackQuantity` | Display preview | Not sent as the charge |
| Local `formatBytes` vs `*_human` | Presentation | Prefer API fields later |
| Payment channel client search; menus client plain table | Accepted consumer choice | |
| Blade JS fee re-quote | Blade also did this | Baseline mimic |
| SPA cannot call `PaymentGateway::defaultForInvoice()` | Server may fall back; SPA skips sync when provider unknown | Skip is safe |

**Correct consumer pattern:** `quota.can_create_workspace`, `slots.can_create`, `claim.can_verify`, flattened channel quotes, invoice fee snapshots, `*_formatted`.
