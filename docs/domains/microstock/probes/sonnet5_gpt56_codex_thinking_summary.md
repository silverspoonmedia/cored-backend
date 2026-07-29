# Sonnet 5 Vision + GPT-5.6 Luna Thinking (Codex Upstream) — Combo Probe

**Date:** 2026-07-28  
**Folder:** `019fa66d-65ae-72ea-bd20-8d0919ec5900`  
**Workspace:** `019fa66d-378e-7102-a396-9d72cd8d592a`  
**Vision routing:** 9Router `internal.vision` → **`anthropic/claude-sonnet-5`** (unchanged)  
**Thinking routing:** 9Router `internal.thinking` → **`gpt-5.6-luna`** via **Codex** upstream (was API on locked production run)  
**Config:** `delivery=url`, `variant_kind=ai_1024`, `thinking_visual_attempts=2`  
**Probe hint:** `"Children doing sports"` (fixed, same as prior model runs)  
**Baseline:** [`sonnet5_gpt56_combo_summary.md`](sonnet5_gpt56_combo_summary.md) (same combo, API thinking upstream)

## Pre-flight

| Check | Result |
|-------|--------|
| Variants ready (3×2) | OK |
| T0 vision (`sonnet5-gpt56-codex-t0`) | **ok** @ 2863ms, `anthropic/claude-sonnet-5`, ~947 tok/s |
| T0t thinking (`sonnet5-gpt56-codex-t0`) | **ok** @ 2005ms, `gpt-5.6-luna`, ~220 tok/s |
| Effective `config_delivery` | `url` |
| `image_payload_bytes` | ~335–341 |

Aggregate: `collection_019fa66d-65ae-72ea-bd20-8d0919ec5900_20260728_043309.json`

**T0t note:** Codex text preflight ~220 tok/s vs API baseline ~1614 tok/s (~7× slower). Resolved model string is identical (`gpt-5.6-luna`); only upstream path differs.

## Per-file results (T3 + T6 with `stage_metrics`)

| File | T3 ms | T3 tok/s | Vision T6 ms/tok/s | Thinking T6 ms/tok/s | multimodal? | Title | Keywords (preview) | Gender ok? |
|------|-------|----------|--------------------|-----------------------|-------------|-------|-------------------|------------|
| Group_758girl | 10096 | 358 | 7231 / 501 | 50324 / 92 | **yes** | Cartoon illustration of young **girl** running during sports activity | girl, child, running, sports, exercise, young, cartoon, illustration, sneaker, athlete | **yes** |
| Group_2808 | 7184 | 485 | 7318 / 485 | 50735 / 90 | **yes** | Young **Girl** Running During Sports Activity | girl, child, athlete, running pose, sportswear, sneaker, shorts, shirt, exercise, movement | **yes** |
| Group_2119boy | 5969 | 578 | 5809 / 593 | 45295 / 89 | **yes** | Young **Boy** Runs During Sports Training Session | child, boy, running, sport, athlete, exercise, sneaker, shorts, training, fitness | **yes** |

**T3 pass rate:** 3/3  
**T6 pass rate:** 3/3 (HTTP)  
**Explicit gender accuracy:** **3/3** — matches API baseline  
**Thinking multimodal:** 3/3 (`thinking_multimodal: true`; `stage_metrics.thinking.multimodal: true`)

Per-file reports: `sonnet5-gpt56-codex-thinking-Group_758girl_20260728_043105.json`, `sonnet5-gpt56-codex-thinking-Group_2808_20260728_043211.json`, `sonnet5-gpt56-codex-thinking-Group_2119boy_20260728_043309.json`

## Stage telemetry (`stage_metrics`)

All T6 runs: vision → `anthropic/claude-sonnet-5`; thinking → `gpt-5.6-luna`. No text-only thinking fallback was needed.

| Stage | Codex run | API baseline (locked) |
|-------|-----------|------------------------|
| Vision T6 duration | 5.8–7.3s | 6.1–6.9s |
| Vision TPS | 485–593 | 516–571 |
| Thinking T6 duration | **45–51s** | **9.1–12.4s** |
| Thinking TPS | **89–92** | **540–705** |
| T6 wall-clock | **51–58s** | **15.6–18.5s** |

**Speed:** Vision stage stable between upstreams. Thinking Codex is **~5–7× slower TPS** and adds **~45–51s** per asset vs **~9–12s** on API — T6 wall-clock **~3× longer**. No CF 524 timeouts at `--timeout=300`.

## Quality notes

- **Title/keyword quality:** Comparable to API baseline — explicit girl/boy on all 3 files, hint `"Children doing sports"` did not bleed into wrong gender.
- **758girl / 2808:** Girl labels correct (same as API combo).
- **2119boy:** Boy label correct (same as API combo).
- Keyword counts: 17–19 (within target). Category: `Illustration` on all T6 outputs.
- T3 vision still gender-neutral ("child") on some files; thinking multimodal VERIFY corrects on T6 — same pattern as API run.

## vs API baseline (same combo, different thinking upstream)

| Metric | API thinking | Codex thinking | Delta |
|--------|--------------|----------------|-------|
| T0t tok/s | ~1614 | ~220 | ~7× slower |
| Thinking T6 tok/s | 540–705 | 89–92 | ~6–8× slower |
| T6 wall-clock | 15.6–18.5s | 51–58s | ~3× slower |
| Gender accuracy | 3/3 | 3/3 | same |
| Multimodal thinking | 3/3 | 3/3 | same |
| Resolved models | sonnet + luna | sonnet + luna | same strings |

## Probe recommendation (not production)

**Quality:** Codex thinking upstream delivers the same gender + SEO outcome as API on this collection — no reason to switch upstream for quality alone.

**Latency:** Codex thinking is not production-viable at current observed TPS (~90 tok/s vs ~600 tok/s API). Per-asset T6 adds ~35–40s extra wall-clock. Production stack remains locked to API thinking path per [`sonnet5_gpt56_combo_summary.md`](sonnet5_gpt56_combo_summary.md).

Re-test Codex only if 9Router upstream TPS improves materially; compare T0t + T6 `stage_metrics.thinking.tokens_per_second` before any routing change.

## Pass criteria (plan)

| Check | Expected | Actual |
|-------|----------|--------|
| T0 + T0t preflight | sonnet + gpt-5.6-luna resolved | **pass** |
| T3 per file | ok + TPS | **pass** (3/3) |
| T6 `stage_metrics` + multimodal | surfaced in probe | **pass** (3/3 multimodal) |
| Title/keywords vs API baseline | documented | **pass** (3/3 gender) |
| TPS delta documented | Codex slower | **pass** (~6–8× thinking TPS) |

## Re-run

```powershell
php scripts/microstock/generate_variants.php 019fa66d-65ae-72ea-bd20-8d0919ec5900
php scripts/microstock/probe_ai.php --timeout=300 --only=T0,T0t --folder=019fa66d-65ae-72ea-bd20-8d0919ec5900 --label=sonnet5-gpt56-codex-t0
php scripts/microstock/probe_collection.php 019fa66d-65ae-72ea-bd20-8d0919ec5900 --timeout=300 --only=T3,T6 --label=sonnet5-gpt56-codex-thinking
```
