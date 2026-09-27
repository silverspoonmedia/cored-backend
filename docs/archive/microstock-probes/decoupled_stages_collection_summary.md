# Decoupled Stage Pipeline — Collection E2E Probe

**Date:** 2026-07-28  
**Folder:** `019fa752-74ba-7274-9ebb-83ef34c18cf5`  
**Workspace:** `019fa752-4dd0-70a9-ab54-0ecaddf50461`  
**Vision routing:** 9Router `internal.vision` → **`anthropic/claude-sonnet-5`**  
**Thinking routing:** 9Router `internal.thinking` → **`gpt-5.6-luna`** (Codex-like TPS ~78–110 tok/s)  
**Config:** `delivery=url`, `variant_kind=ai_1024`, `thinking_visual_attempts=2`  
**Probe hint:** `"Children doing sports activity"`  
**Labels:** `decoupled-probe-legacy` (monolithic `generate()`), `decoupled-probe-e2e` (real jobs + DB stage columns)

## Probe paths

| Script | What it exercises | Writes `ai_vision` / `ai_thinking` / `ai_result`? |
|--------|-------------------|---------------------------------------------------|
| `probe_collection.php` + `probe_ai.php` | Monolithic `HttpAiMetadataService::generate()` | **No** — T6 `stage_metrics` only in JSON artifact |
| `probe_collection_stages.php` | `ProcessCollection` → vision batch → per-asset thinking (sync queue) | **Yes** — persisted stage columns + billing |

E2E artifact: `storage/app/probe_results/collection_stages_019fa752-74ba-7274-9ebb-83ef34c18cf5_20260728_063251.json`  
Legacy aggregate: `storage/app/probe_results/collection_019fa752-74ba-7274-9ebb-83ef34c18cf5_20260728_061357.json`

## Zombie run (why 3600s felt stale)

First E2E attempt hit folder status **`processing`** with **zero** `microstock_asset_metadata` rows — a zombie state (analyze dispatched to async queue or metadata wiped after `migrate:fresh`, but folder never left `processing`). The probe entered poll-only mode and would have waited up to `--wait-timeout` with nothing changing.

**Fix applied to `probe_collection_stages.php`:**

- Default `--wait-timeout` lowered from **3600 → 900** s (15 min ceiling; this 3-file run finished in **~176 s**).
- **`--force`**: delete stale metadata, mark `failed`, re-lock with existing hint, re-analyze (sync).
- **Zombie fail-fast**: if `processing` + 0 metadata rows for **60 s** in poll-only mode → exit with message to use `--force`.
- **Progress log** every 30 s during wait.

Re-run command used:

```bash
php scripts/microstock/probe_collection_stages.php 019fa752-74ba-7274-9ebb-83ef34c18cf5 \
  --label=decoupled-probe-e2e --force --wait-timeout=900
```

## E2E per-file results (decoupled pipeline)

| File | vision ms / tok/s | thinking ms / tok/s | stages | Title (thinking) | Gender ok? |
|------|-------------------|---------------------|--------|------------------|------------|
| Group_758girl | 8576 / 415 | 60107 / 87 | vision ok, thinking ok, result ok | Cartoon girl running in sportswear during exercise | **yes** |
| Group_2119boy | 7203 / 498 | 58833 / 79 | vision ok, thinking ok, result ok | Boy Running During Sports Activity Illustration | **yes** |
| Group_2808 | 8545 / 420 | 32579 / 110 | vision ok, thinking ok, result ok | Cartoon girl running during sports activity | **yes** |

**Pass rate:** 3/3 all stages terminal; folder → **`review`**.  
**Explicit gender:** **3/3** (girl / boy / girl).  
**Collection wall-clock (sync, 3 files sequential thinking):** **176140 ms** (~2m 56s).

Vision draft titles (in `ai_vision.output.title`) differ from final thinking titles — expected; thinking is source of truth when `ai_result.source = thinking`.

## Legacy vs E2E stage timing (same stack, same day)

| File | Legacy vision T6 ms | E2E vision ms | Legacy thinking T6 ms | E2E thinking ms |
|------|---------------------|---------------|------------------------|-----------------|
| Group_758girl | 7415 | 8576 | 51060 | 60107 |
| Group_2119boy | 5723 | 7203 | 68637 | 58833 |
| Group_2808 | ~6800* | 8545 | ~61000* | 32579 |

\*2808 legacy thinking from aggregate `stage_metrics` (68159 ms T6 wall, thinking portion in file report).

Per-stage durations are in the same ballpark — E2E adds job orchestration overhead but no separate HTTP path; both hit the same `analyzeVision()` / thinking endpoints. As of the dual-lane pipeline refactor, collection analyze dispatches per-file vision jobs on `microstock-collection-vision` with thinking on `microstock-collection-thinking` (pipeline overlap: max 1 vision + 1 thinking per folder).

## Takeaways

1. **3600 s was a safety ceiling, not expected runtime.** For 3 files on Codex thinking, budget **~3–5 min** sync; use `--wait-timeout=900` unless you have a huge batch.
2. **Stale = zombie `processing`.** Always check metadata row count; use `--force` after failed/interrupted runs or post-`migrate:fresh`.
3. **Legacy probes remain valid for TPS/model routing**; use **`probe_collection_stages.php`** when validating decoupled stage columns, billing, and folder lifecycle.
4. Thinking TPS **~78–110** confirms Codex upstream — same as legacy T6 on this folder, not API (~500+ tok/s).

## Related

- Stage decoupling MVP: [`../../domains/microstock/reference-pipeline.md`](../../domains/microstock/reference-pipeline.md#stage-decoupling-mvp--dual-lane-pipeline)
- Legacy TPS baseline (other folder): [`sonnet5_gpt56_codex_thinking_summary.md`](sonnet5_gpt56_codex_thinking_summary.md)
