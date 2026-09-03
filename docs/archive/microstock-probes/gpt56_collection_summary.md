# GPT-5.6 — Collection Probe

**Date:** 2026-07-28  
**Folder:** `019fa66d-65ae-72ea-bd20-8d0919ec5900`  
**Workspace:** `019fa66d-378e-7102-a396-9d72cd8d592a`  
**Vision routing:** 9Router `internal.vision` → **`gpt-5.6-luna`** (resolved on all runs)  
**Thinking:** unchanged (`internal.thinking`)  
**Config:** `delivery=url`, `variant_kind=ai_1024`  
**Rate card:** `gpt-5.6`, `gpt-5.6-sol`, `gpt-5.6-terra`, `gpt-5.6-luna` added

## Pre-flight

| Check | Result |
|-------|--------|
| Variants ready (3×2) | OK |
| T0 (`gpt-5.6-t0`) | **ok** @ 2523ms, `resolved_model=gpt-5.6-luna`, ~1837 tok/s |
| Effective `config_delivery` | `url` |
| `image_payload_bytes` | ~335–341 |

## Batch results (`probe_collection_folder.php --label=gpt-5.6`)

Aggregate: `collection_019fa66d-65ae-72ea-bd20-8d0919ec5900_20260728_030439.json`

| File | T3 ms | T3 tok/s | resolved_model | T6 ms | Title | Keywords (preview) | Pass? |
|------|-------|----------|----------------|-------|-------|-------------------|-------|
| Group_758girl | 8104 | 786.65 | gpt-5.6-luna | 20167 | Cartoon **boy** running in a kids sports activity | boy, child, running, run, sport, kid sport, physical activity, exercise, cartoon character, sneaker | partial (wrong gender in T6 title) |
| Group_2808 | 9182 | 682.86 | gpt-5.6-luna | 18060 | Cartoon girl running in sportswear for kids sports | girl, child, running, sport, kid activity, jogging, sneaker, shorts, shirt, athlete | **ok** |
| Group_2119boy | 6619 | 928.09 | gpt-5.6-luna | 17514 | Cartoon boy running with raised arms in sports clothes | boy, child, running, sport, sneaker, shorts, shirt, raised arm, smile, cartoon | **ok** |

**T3 pass rate:** 3/3  
**T6 pass rate:** 3/3 (HTTP); 2/3 title accuracy (758girl mis-gendered after thinking stage)

Per-file reports: `gpt-5.6-Group_758girl_20260728_030346.json`, `gpt-5.6-Group_2808_20260728_030414.json`, `gpt-5.6-Group_2119boy_20260728_030439.json`

## Quality notes

- **758girl T3 vision** correctly identified girl (`"title":"Girl running in sportswear with athletic shoes"`).
- **758girl T6 thinking** overwrote to boy-themed title/keywords — likely collection hint `"Children doing sports"` bleeding into thinking curation. Worth comparing against per-file analyze without shared hint.
- Keyword counts: 22–25 (within 15–30 target). Top keywords are literal/contextual (subject + sport).

## vs prior models (same folder, `delivery=url`)

| Model | T3 typical | T3 tok/s | T6 pass | Notes |
|-------|------------|----------|---------|-------|
| grok-grok-4.5-high | ~46s | ~133 | high | baseline |
| gpt-5.4 | ~109s / 524 | ~71 / n/a | 1/3 batch | CF timeout variance |
| kimi-k3 | ~1–2s | n/a | 0/3 | upstream 400 unavailable |
| **gpt-5.6-luna** | **6.6–9.2s** | **683–928** | **3/3** | fastest reliable vision on this stack |

**Speed:** GPT-5.6 Luna vision is **~5–16× faster** than grok (~46s) and **~12–17× faster** than gpt-5.4 when it passes (~109s). No 524 timeouts observed.

## Pass criteria (plan)

| Check | Expected | Actual |
|-------|----------|--------|
| Rate card gpt-5.6 tiers | added | **pass** |
| T0 gpt-5.6 family | ok | **pass** (`gpt-5.6-luna`) |
| Variants ready | ai_1024 ready | **pass** |
| T3 per file | ok + TPS | **pass** (3/3) |
| T6 title/keywords | captured | **pass** (review 758girl gender) |

## Re-run

```powershell
php storage/app/generate_folder_variants.php 019fa66d-65ae-72ea-bd20-8d0919ec5900
php storage/app/probe_microstock_ai.php --timeout=300 --only=T0 --folder=019fa66d-65ae-72ea-bd20-8d0919ec5900 --label=gpt-5.6-t0
php storage/app/probe_collection_folder.php 019fa66d-65ae-72ea-bd20-8d0919ec5900 --timeout=300 --label=gpt-5.6
```
