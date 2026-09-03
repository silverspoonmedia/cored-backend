# Sonnet 5 Vision + GPT-5.6 Thinking — Combo Probe

**Date:** 2026-07-28  
**Folder:** `019fa66d-65ae-72ea-bd20-8d0919ec5900`  
**Workspace:** `019fa66d-378e-7102-a396-9d72cd8d592a`  
**Vision routing:** 9Router `internal.vision` → **`anthropic/claude-sonnet-5`**  
**Thinking routing:** 9Router `internal.thinking` → **`gpt-5.6-luna`** (resolved on all runs)  
**Config:** `delivery=url`, `variant_kind=ai_1024`, `thinking_visual_attempts=2`  
**Probe hint:** `"Children doing sports"` (fixed, same as prior model runs)

## Pre-flight

| Check | Result |
|-------|--------|
| Variants ready (3×2) | OK |
| T0 vision (`sonnet5-gpt56-t0`) | **ok** @ 2903ms, `anthropic/claude-sonnet-5`, ~934 tok/s |
| T0t thinking (`sonnet5-gpt56-t0`) | **ok** @ 2872ms, `gpt-5.6-luna`, ~1614 tok/s |
| Effective `config_delivery` | `url` |
| `image_payload_bytes` | ~335–341 |

Aggregate: `collection_019fa66d-65ae-72ea-bd20-8d0919ec5900_20260728_032616.json`

## Per-file results (T3 + T6 with `stage_metrics`)

| File | T3 ms | T3 tok/s | Vision T6 ms/tok/s | Thinking T6 ms/tok/s | multimodal? | Title | Keywords (preview) | Gender ok? |
|------|-------|----------|--------------------|-----------------------|-------------|-------|-------------------|------------|
| Group_758girl | 7367 | 487 | 6068 / 571 | 12441 / 540 | **yes** | Young **Girl** Running in Athletic Sportswear | girl, running, young athlete, sportswear, sneaker, shorts, exercise, fitness, youth, motion | **yes** |
| Group_2808 | 6302 | 550 | 6850 / 516 | 11142 / 589 | **yes** | **Girl** Running During Children Sports Activity | girl, child, running, sport, athlete, exercise, fitness, youth, physical education, movement | **yes** |
| Group_2119boy | 7634 | 469 | 6447 / 538 | 9124 / 705 | **yes** | Cartoon **boy** running in sportswear during sports activity | boy, running, sportswear, sneaker, shorts, sport, exercise, motion, physical activity, cartoon | **yes** |

**T3 pass rate:** 3/3  
**T6 pass rate:** 3/3 (HTTP)  
**Explicit gender accuracy:** **3/3** — best result on this collection hint so far  
**Thinking multimodal:** 3/3 (`thinking_multimodal: true` on winning attempt; `stage_metrics.thinking.multimodal: true`)

Per-file reports: `sonnet5-gpt56-Group_758girl_20260728_032527.json`, `sonnet5-gpt56-Group_2808_20260728_032552.json`, `sonnet5-gpt56-Group_2119boy_20260728_032616.json`

## Stage telemetry (`stage_metrics`)

`HttpAiMetadataService::generate()` now returns per-stage metrics surfaced in T6 probe output:

| Stage | Fields |
|-------|--------|
| `vision` | `duration_ms`, `resolved_model`, `multimodal`, `tokens_per_second`, `http_status` |
| `thinking` | same; winning attempt is the last successful entry |

All T6 runs: vision → `anthropic/claude-sonnet-5`; thinking → `gpt-5.6-luna`. No text-only thinking fallback was needed.

## Quality notes

- **Hypothesis confirmed:** Sonnet vision reads gender from the image (T3 often gender-neutral "child"); GPT-5.6 thinking with **multimodal VERIFY** (image + draft) corrects to explicit girl/boy keywords without hint bleed.
- **758girl:** prior `gpt-5.6-luna` both-stages run mis-gendered T6 to boy; combo run titles **Girl** correctly.
- **2808:** prior `claude-opus-5` forced boy; combo run titles **Girl** correctly.
- **2119boy:** consistent boy label across combos that got gender right.
- Keyword counts: 18–20 (within target). Category: `Illustration` on all T6 outputs.
- T6 wall-clock **15.6–18.5s** (vision ~6.1–6.9s + thinking ~9.1–12.4s).

## vs prior runs (same folder, same hint, `delivery=url`)

| Combo | Vision | Thinking | Gender | Notes |
|-------|--------|----------|--------|-------|
| sonnet-5 only (both stages sonnet) | sonnet | sonnet | 0/3 explicit; neutral **child** | no mis-gender; weak SEO |
| opus-5 vision (both opus) | opus | opus | 1/3 | all forced **boy** |
| gpt-5.6-luna both | luna | luna | 2/3 | 758girl T6 → boy; fastest vision TPS |
| **sonnet vision + gpt-5.6 thinking** | **sonnet** | **gpt-5.6-luna** | **3/3** | multimodal thinking on all files; best gender+SEO balance |

| Model combo | T3 typical | T3 tok/s | T6 typical | Thinking tok/s | Gender |
|-------------|------------|----------|------------|----------------|--------|
| gpt-5.6-luna both | 6.6–9.2s | 683–928 | 17–20s | n/a (no stage split) | 2/3 |
| claude-sonnet-5 both | 6.0–7.9s | 454–577 | 13–20s | n/a | 0/3 explicit |
| claude-opus-5 both | 7.0–7.9s | 448–508 | 15–17s | n/a | 1/3 |
| **sonnet + gpt-5.6** | **6.3–7.6s** | **469–550** | **15.6–18.5s** | **540–705** | **3/3** |

**Speed:** Vision TPS lower than gpt-5.6-luna-only (~470–550 vs ~680–930) but comparable wall-clock. Thinking adds ~9–12s at ~540–705 tok/s. No CF 524 timeouts.

## Pass criteria (plan)

| Check | Expected | Actual |
|-------|----------|--------|
| `stage_metrics` in `generate()` | vision + thinking entries | **pass** |
| T0 + T0t preflight | sonnet + gpt-5.6 resolved | **pass** |
| T3 per file | ok + TPS | **pass** (3/3) |
| T6 `stage_metrics` + multimodal flag | surfaced in probe | **pass** (3/3 multimodal) |
| Title/keywords vs prior runs | documented | **pass** (3/3 gender) |

## Re-run

```powershell
php scripts/microstock/generate_variants.php 019fa66d-65ae-72ea-bd20-8d0919ec5900
php scripts/microstock/probe_ai.php --timeout=300 --only=T0,T0t --folder=019fa66d-65ae-72ea-bd20-8d0919ec5900 --label=sonnet5-gpt56-t0
php scripts/microstock/probe_collection.php 019fa66d-65ae-72ea-bd20-8d0919ec5900 --timeout=300 --label=sonnet5-gpt56
```
