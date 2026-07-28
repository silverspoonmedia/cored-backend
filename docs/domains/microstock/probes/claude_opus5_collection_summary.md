# Claude Opus 5 — Collection Probe

**Date:** 2026-07-28  
**Folder:** `019fa66d-65ae-72ea-bd20-8d0919ec5900`  
**Workspace:** `019fa66d-378e-7102-a396-9d72cd8d592a`  
**Vision routing:** 9Router `internal.vision` → **`anthropic/claude-opus-5`** (resolved on all runs)  
**Thinking:** unchanged (`internal.thinking`)  
**Config:** `delivery=url`, `variant_kind=ai_1024`  
**Rate card:** `claude-opus-5` + `anthropic/claude-opus-5` @ $5 / $25 per 1M tokens

## Pre-flight

| Check | Result |
|-------|--------|
| Variants ready (3×2) | OK |
| T0 (`claude-opus-5-t0`) | **ok** @ 3366ms, `resolved_model=anthropic/claude-opus-5`, ~807 tok/s |
| Effective `config_delivery` | `url` |
| `image_payload_bytes` | ~335–341 |

## Batch results (`probe_collection_folder.php --label=claude-opus-5`)

Aggregate: `collection_019fa66d-65ae-72ea-bd20-8d0919ec5900_20260728_031014.json`

| File | T3 ms | T3 tok/s | resolved_model | T6 ms | Title | Keywords (preview) | Pass? |
|------|-------|----------|----------------|-------|-------|-------------------|-------|
| Group_758girl | 7894 | 447.94 | anthropic/claude-opus-5 | 14972 | Cartoon **boy** running outdoors during sports exercise | child, boy, running, sport, exercise, kid, jogging, activity, training, motion | partial (wrong gender; vision T3 also says boy) |
| Group_2808 | 7559 | 468.85 | anthropic/claude-opus-5 | 16718 | Cartoon **boy** running outdoors during sports exercise | child, boy, running, sport, exercise, kid, activity, training, fitness, motion | partial (2808 was girl in gpt-5.6-luna run) |
| Group_2119boy | 6985 | 508.09 | anthropic/claude-opus-5 | 15697 | Cartoon boy running in sportswear during sport activity | boy, child, running, sport, exercise, kid, sportswear, sneaker, athlete, physical activity | **ok** |

**T3 pass rate:** 3/3  
**T6 pass rate:** 3/3 (HTTP); 1/3 title accuracy (only 2119boy correct gender)

Per-file reports: `claude-opus-5-Group_758girl_20260728_030925.json`, `claude-opus-5-Group_2808_20260728_030950.json`, `claude-opus-5-Group_2119boy_20260728_031014.json`

## Quality notes

- **Collection hint bleed:** probe uses fixed hint `"Children doing sports"`. Claude Opus 5 defaults all assets to **boy** in both vision T3 and thinking T6 — worse than gpt-5.6-luna where only 758girl T6 regressed after thinking.
- **2808 regression:** gpt-5.6-luna correctly titled as girl; Claude labels both girl files as boy.
- Keyword counts: 20–21 (within target). Keywords are sport-themed but gender-wrong on 2/3 files.

## vs prior models (same folder, `delivery=url`)

| Model | T3 typical | T3 tok/s | T6 pass | Quality notes |
|-------|------------|----------|---------|---------------|
| grok-grok-4.5-high | ~46s | ~133 | high | baseline |
| gpt-5.4 url | ~109s / 524 | ~71 / n/a | 1/3 | CF variance |
| kimi-k3 | ~1–2s | n/a | 0/3 | upstream 400 |
| gpt-5.6-luna | ~7–9s | ~683–928 | 3/3 | 2/3 gender ok; 758girl T6 only |
| **anthropic/claude-opus-5** | **7.0–7.9s** | **448–508** | **3/3** | 1/3 gender ok; hint bleed in vision |

**Speed:** Comparable wall-clock to gpt-5.6-luna (~7–8s T3) but lower TPS (~450 vs ~700–930). T6 ~15–17s. No 524 timeouts.

## Pass criteria (plan)

| Check | Expected | Actual |
|-------|----------|--------|
| Rate card `claude-opus-5` | added | **pass** (+ `anthropic/claude-opus-5` slug from API) |
| T0 claude-opus-5 | ok | **pass** |
| Variants ready | ai_1024 ready | **pass** |
| T3 per file | ok + TPS | **pass** (3/3) |
| T6 title/keywords | captured | **pass** (review gender/hint bleed) |

## Re-run

```powershell
php storage/app/generate_folder_variants.php 019fa66d-65ae-72ea-bd20-8d0919ec5900
php storage/app/probe_microstock_ai.php --timeout=300 --only=T0 --folder=019fa66d-65ae-72ea-bd20-8d0919ec5900 --label=claude-opus-5-t0
php storage/app/probe_collection_folder.php 019fa66d-65ae-72ea-bd20-8d0919ec5900 --timeout=300 --label=claude-opus-5
```
