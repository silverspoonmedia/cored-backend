# Billing smoke — free vs paid collection (2026-08-01)

**Workspace:** `019fbc21-9faf-700c-94a4-c981f58796aa`  
**Hint:** Children doing sports activity (structured Lock & Describe sections)  
**Pipeline:** `probe_collection_stages.php` (sync queue, no workers)  
**Inference provider:** `openwebui-9router` (`inference_provider_id` populated on all usage events)

| Collection | Folder ID | `collection_billing_mode` |
|------------|-----------|---------------------------|
| Free | `019fbc21-eff1-72af-b14c-494ecbe14328` | `free` |
| Paid | `019fbc22-1585-72f4-86d5-ac33faf984d5` | `paid` |

Same 3 files in both folders: `Group_2119boy.png`, `Group_2808.png`, `Group_758girl.png`.

## Artifacts

| Artifact | Path |
|----------|------|
| Free probe JSON | `storage/app/probe_results/collection_stages_019fbc21-eff1-72af-b14c-494ecbe14328_20260801_075140.json` |
| Paid probe JSON | `storage/app/probe_results/collection_stages_019fbc22-1585-72f4-86d5-ac33faf984d5_20260801_075331.json` |
| Billing comparison | `storage/app/probe_results/billing_smoke_comparison.json` |
| Balance before | `storage/app/probe_results/billing_smoke_balance_before.json` |

## Pass rate & wall-clock

| | Free | Paid |
|--|------|------|
| Files / pass | 3 files, **1/3 generated** (2 vision failures) | 3 files, **3/3 generated** |
| Folder status | `review` | `review` |
| Collection wall-clock | ~442 s (probe hung on watchdog loop; see notes) | **104.9 s** |

Free vision failures: `Group_758girl.png` and `Group_2119boy.png` — generic `AI metadata generation failed.` with empty `stage_metrics` (~126 s per failed vision attempt).

## TPS (tokens per second)

### Paid (`internal.sspn_pro_*` → sonnet vision + luna thinking)

| File | Vision tok/s | Thinking tok/s | Vision model | Thinking model |
|------|--------------|----------------|--------------|----------------|
| Group_2119boy | 399.57 | 327.27 | `anthropic/claude-sonnet-5` | `gpt-5.6-luna` |
| Group_2808 | 323.44 | 397.46 | `anthropic/claude-sonnet-5` | `gpt-5.6-luna` |
| Group_758girl | 417.47 | 232.01 | `anthropic/claude-sonnet-5` | `gpt-5.6-luna` |

Vision TPS range: **323–417** tok/s. Thinking TPS range: **232–397** tok/s.

### Free (`internal.sspn_free_*` — successful file only)

| File | Vision tok/s | Thinking tok/s | Resolved model |
|------|--------------|----------------|----------------|
| Group_2808 | 70.4 | 79.49 | `gpt-5.6-luna` (both stages) |

Free tier used cheaper upstream aliases; resolved model still `gpt-5.6-luna` on the one successful asset.

## Billing

| | Free | Paid |
|--|------|------|
| `billing_source` on events | `daily_free` | `prepaid` |
| Usage events | 2 (1 asset × vision + thinking) | 6 (3 assets × vision + thinking) |
| Total tokens | 14,058 | 33,123 |
| Provider cost | $0.07 | $0.07 |
| Billable (recorded) | $0.09 | $0.09 |
| Credit debited | **$0.00** | **$0.09** |
| Prepaid balance | $50.00 → $50.00 | $50.00 → **$49.91** |
| `inference_provider_id` | populated | populated |
| Rate snapshots | 1 (`gpt-5.6-luna`) | 2 (sonnet + luna) |

Paid debit **$0.09** matches sum of prepaid `billable_micro_usd` (92,491 µUSD). Free run recorded billable on usage rows but settled with `billable_micro_usd: 0` on `ai_billing` (no ledger debit).

Alias models:

- Free: `internal.sspn_free_vision`, `internal.sspn_free_thinking`
- Paid: `internal.sspn_pro_vision`, `internal.sspn_pro_thinking`

## Operational notes

1. **Variants required** — ran `generate_variants.php` on both folders before probe (`ai_1024` was missing).
2. **Zombie `processing`** — paid folder was stuck `processing` with no metadata; reset to `failed` before paid run. Another folder in `processing` causes `AnalysisWatchdog` to re-dispatch every 60s under sync queue and can hang `probe_collection_stages.php` indefinitely.
3. **Probe script fixes** (2026-08-01):
   - `--force` re-lock preserves `collection_billing_mode`
   - After `analyze()`, clears `jobs` table to stop watchdog respawn loop on sync queue
4. **Re-run helpers:** `scripts/microstock/billing_smoke_preflight.php`, `billing_smoke_compare.php`, `billing_smoke_export_probe_json.php`

## Takeaways

1. **Billing path works** — prepaid debits credits; daily_free records usage without debit; provider-first snapshots and `inference_provider_id` populated.
2. **Model routing differs by tier** — paid vision hits Sonnet 5 (~400 tok/s); free vision on failed runs used free alias (successful file still resolved to luna at ~70 tok/s).
3. **Free run quality** — 2/3 vision failures on free tier suggest upstream flakiness or free-model routing issues; paid 3/3 on identical assets confirms parity of files, not of free upstream reliability.
4. **Cost parity (successful work)** — one free asset ≈ $0.07 provider / $0.09 billable recorded; three paid assets ≈ same provider total with $0.09 total billable debit (scale is per-call, not per-file list price).

## Related

- Provider-first inference billing: [`../../inference/README.md`](../../inference/README.md)
- Decoupled stage E2E baseline: [`decoupled_stages_collection_summary.md`](decoupled_stages_collection_summary.md)
