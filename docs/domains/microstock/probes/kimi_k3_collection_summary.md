# Kimi K3 — Collection Probe

**Date:** 2026-07-28  
**Folder:** `019fa66d-65ae-72ea-bd20-8d0919ec5900`  
**Workspace:** `019fa66d-378e-7102-a396-9d72cd8d592a`  
**Vision routing:** 9Router `internal.vision` → `dahono/kimi-k3`  
**Thinking:** unchanged (`internal.thinking`)  
**Config:** `delivery=url`, `variant_kind=ai_1024`  
**Rate card:** `kimi-k3` added @ $3 / $15 per 1M tokens (input/output)

## Pre-flight

| Check | Result |
|-------|--------|
| Variants ready (3×2) | OK |
| T0 (`kimi-k3-t0`) | **HTTP 400** @ 7078ms |
| Effective `config_delivery` | `url` |
| `image_payload_bytes` | ~335–341 |

## Batch results (`probe_collection_folder.php --label=kimi-k3`)

Aggregate: `collection_019fa66d-65ae-72ea-bd20-8d0919ec5900_20260728_025709.json`

| File | T3 ms | T3 tok/s | T3 status | T6 ms | T6 status | Title | Keywords (preview) | Pass? |
|------|-------|----------|-----------|-------|-----------|-------|-------------------|-------|
| Group_758girl | 1822 | n/a | HTTP 400 | 1743 | error | — | — | fail |
| Group_2808 | 1760 | n/a | HTTP 400 | 1559 | error | — | — | fail |
| Group_2119boy | 858 | n/a | HTTP 400 | 1195 | error | — | — | fail |

**T3 pass rate:** 0/3  
**T6 pass rate:** 0/3  
**TPS:** not measurable — all requests rejected before inference

Per-file reports: `kimi-k3-Group_758girl_20260728_025702.json`, `kimi-k3-Group_2808_20260728_025706.json`, `kimi-k3-Group_2119boy_20260728_025709.json`

## Root cause

Upstream error (full body from vision probe):

```
Model tidak tersedia. Pilih model lain dari daftar.
(type: invalid_request_error, code: upstream_error)
```

Routing to `dahono/kimi-k3` works; the **provider does not expose `kimi-k3`** on this 9Router path yet. This is an infra/config issue on Open WebUI / 9Router / dahono — not monolith code, delivery mode, or variants.

## Pass criteria (plan)

| Check | Expected | Actual |
|-------|----------|--------|
| Rate card `kimi-k3` | added | **pass** |
| Variants ready | `ai_1024` ready | **pass** |
| T0 `resolved_model=kimi-k3` | ok | **fail** (400 upstream) |
| T3 per file | ok + TPS | **fail** (0/3) |
| T6 title/keywords | captured | **fail** (no successful runs) |

## vs prior models (same folder, `delivery=url`)

| Model | T3 typical | T3 tok/s | T6 pass rate | Notes |
|-------|------------|----------|--------------|-------|
| grok-grok-4.5-high | ~46s | ~133 | high | baseline |
| gpt-5.4 | ~109s (when pass) / 524 | ~71 (when pass) | 1/3 batch | CF timeout variance |
| **kimi-k3** | **~1–2s** | **n/a** | **0/3** | upstream 400 — model unavailable |

## Next steps

1. Fix 9Router/Open WebUI combo so `dahono/kimi-k3` (or correct provider slug) is actually available.
2. Re-run after fix:
   ```powershell
   php storage/app/probe_microstock_ai.php --timeout=300 --only=T0 --folder=019fa66d-65ae-72ea-bd20-8d0919ec5900 --label=kimi-k3-t0
   php storage/app/probe_collection_folder.php 019fa66d-65ae-72ea-bd20-8d0919ec5900 --timeout=300 --label=kimi-k3
   ```
3. Compare title/keyword quality and TPS against grok/gpt-5.4 once vision succeeds.
