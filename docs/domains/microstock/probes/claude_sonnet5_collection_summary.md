# Claude Sonnet 5 — Collection Probe

**Date:** 2026-07-28  
**Folder:** `019fa66d-65ae-72ea-bd20-8d0919ec5900`  
**Workspace:** `019fa66d-378e-7102-a396-9d72cd8d592a`  
**Vision routing:** 9Router `internal.vision` → **`anthropic/claude-sonnet-5`** (resolved on all runs)  
**Thinking:** unchanged (`internal.thinking`)  
**Config:** `delivery=url`, `variant_kind=ai_1024`  
**Rate card:** `claude-sonnet-5` + `anthropic/claude-sonnet-5` @ **$2 / $10** per 1M (intro through Aug 31, 2026; standard $3/$15 from Sep 1)

## Pre-flight

| Check | Result |
|-------|--------|
| Variants ready (3×2) | OK |
| T0 (`claude-sonnet-5-t0`) | **ok** @ 3040ms, `resolved_model=anthropic/claude-sonnet-5`, ~892 tok/s |
| Effective `config_delivery` | `url` |
| `image_payload_bytes` | ~335–341 |

## Batch results (`probe_collection_folder.php --label=claude-sonnet-5`)

Aggregate: `collection_019fa66d-65ae-72ea-bd20-8d0919ec5900_20260728_031601.json`

| File | T3 ms | T3 tok/s | resolved_model | T6 ms | Title | Keywords (preview) | Pass? |
|------|-------|----------|----------------|-------|-------|-------------------|-------|
| Group_758girl | 7921 | 454.36 | anthropic/claude-sonnet-5 | 20312 | Cartoon **child** running in sport illustration for kids | child running, kid sport, young athlete, cartoon character, physical education, jogging, race, exercise, school activity, youth fitness | partial (gender-neutral; no "girl") |
| Group_2808 | 7367 | 485.68 | anthropic/claude-sonnet-5 | 15303 | **Child** runs outdoors during a sports training activity | child, running, kid, sport, athlete, outdoor, exercise, training, fitness, active | partial (gender-neutral; no "girl") |
| Group_2119boy | 6033 | 577.32 | anthropic/claude-sonnet-5 | 13238 | **Child** Running Outdoors During Sports Activity | child, running, sport, outdoor, exercise, kid, active, play, motion, training | partial (gender-neutral; boy file ok but not explicit) |

**T3 pass rate:** 3/3  
**T6 pass rate:** 3/3 (HTTP)  
**Explicit gender accuracy:** 0/3 (all gender-neutral "child") — but **no wrong-gender labels** (better than claude-opus-5 1/3 and gpt-5.6-luna 2/3 with one mis-gender)

Per-file reports: `claude-sonnet-5-Group_758girl_20260728_031517.json`, `claude-sonnet-5-Group_2808_20260728_031541.json`, `claude-sonnet-5-Group_2119boy_20260728_031601.json`

## Quality notes

- **Hint bleed (mitigated):** probe hint `"Children doing sports"` no longer forces **boy** on girl files (unlike claude-opus-5). Sonnet defaults to gender-neutral **child** / **kid** — safer but loses searchable gender keywords for girl assets.
- **Category variance:** 758girl → `Illustration`; 2808 + 2119boy → `People` (marketplace rules prefer single category; cartoon assets should be `Illustration`).
- **Keyword style:** more conceptual phrases (`child running`, `youth fitness`, `healthy lifestyle`) vs literal subject keywords in gpt-5.6-luna run.
- Keyword counts: 20–21 (within target).

## vs prior models (same folder, `delivery=url`)

| Model | T3 typical | T3 tok/s | T6 pass | Gender handling |
|-------|------------|----------|---------|-----------------|
| grok-grok-4.5-high | ~46s | ~133 | high | — |
| gpt-5.6-luna | ~7–9s | ~683–928 | 3/3 | 2/3 correct; 758girl T6 → boy |
| anthropic/claude-opus-5 | ~7–8s | ~448–508 | 3/3 | 1/3; all forced **boy** |
| **anthropic/claude-sonnet-5** | **6.0–7.9s** | **454–577** | **3/3** | 0/3 explicit gender; **neutral, no mis-gender** |

**Speed:** Fastest T3 so far on 2119boy (6.0s). T6 **13–20s**. Comparable to gpt-5.6-luna / claude-opus-5 wall-clock. No 524 timeouts.

**Vision model hunt:** Sonnet 5 trades explicit gender keywords for hint-resilience — best balance so far on this collection hint, but still not ideal for microstock SEO (buyers search "girl running", not "child running").

## Pass criteria (plan)

| Check | Expected | Actual |
|-------|----------|--------|
| Rate card sonnet-5 | added | **pass** |
| T0 sonnet-5 family | ok | **pass** |
| Variants ready | ai_1024 ready | **pass** |
| T3 per file | ok + TPS | **pass** (3/3) |
| T6 title/keywords | captured | **pass** |

## Re-run

```powershell
php storage/app/generate_folder_variants.php 019fa66d-65ae-72ea-bd20-8d0919ec5900
php storage/app/probe_microstock_ai.php --timeout=300 --only=T0 --folder=019fa66d-65ae-72ea-bd20-8d0919ec5900 --label=claude-sonnet-5-t0
php storage/app/probe_collection_folder.php 019fa66d-65ae-72ea-bd20-8d0919ec5900 --timeout=300 --label=claude-sonnet-5
```
