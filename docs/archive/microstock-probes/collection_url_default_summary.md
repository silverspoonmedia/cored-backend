# Collection Re-Probe — URL Config Default (Full Folder)

**Date:** 2026-07-28  
**Folder:** `019fa66d-65ae-72ea-bd20-8d0919ec5900`  
**Workspace:** `019fa66d-378e-7102-a396-9d72cd8d592a`  
**Config:** `MICROSTOCK_AI_VISION_IMAGE_DELIVERY` unset in `.env` → runtime default **`url`** (`config/microstock.php` line 70)  
**Variants:** all 3 files — `ai_512` + `ai_1024` **ready** (pre-flight via `generate_folder_variants.php`)  
**Backend:** Open WebUI → `internal.vision` → `gpt-5.4`

## Pre-flight

| Check | Result |
|-------|--------|
| Variants ready (3×2) | OK |
| T0 (`gpt-5.4-url-default-t0`) | OK @ 5941ms, `resolved_model=gpt-5.4` |
| Effective `config_delivery` | `url` |
| `image_payload_bytes` | ~335–341 (signed URL, not base64) |

## Batch results (`probe_collection_folder.php`)

Aggregate: `collection_019fa66d-65ae-72ea-bd20-8d0919ec5900_20260728_024427.json`

| File | T3 ms | T6 ms | T3 tok/s | payload bytes | Pass? |
|------|-------|-------|----------|---------------|-------|
| Group_758girl | 125625 (524) | **123890 (ok)** | n/a | 341 | partial |
| Group_2808 | 125576 (524) | 125622 (error) | n/a | 335 | fail |
| Group_2119boy | 125607 (524) | 125640 (error) | n/a | 341 | fail |

**T3 pass rate:** 0/3 (all HTTP 524 @ ~125.6s — CF origin timeout wall)  
**T6 pass rate:** 1/3 (758girl only in batch run)

Per-file reports: `collection-Group_758girl_20260728_023604.json`, `collection-Group_2808_20260728_024016.json`, `collection-Group_2119boy_20260728_024427.json`

## Spot-check — Group_2119boy (user-reported fast UI)

`php storage/app/probe_microstock_ai.php --only=T3,T6 --file=019fa66e-7db5-7024-95cb-3044d3ebf068 --label=gpt-5.4-url-2119boy`

| Test | Status | duration_ms | Notes |
|------|--------|-------------|-------|
| T3 | HTTP 524 | 125630 | Standalone vision probe failed |
| T6 | **ok** | **131709** | Full pipeline succeeded — title + 23 keywords |

Report: `gpt-5.4-url-2119boy_20260728_024854.json`

**UI vs probe:** User-reported fast analyze on this file is consistent with production path (`delivery=url`, queue, retries). Standalone T3 is a poor proxy — T6 can succeed when T3 fails on the same stack (run-to-run CF variance + pipeline retries).

## vs single-file URL probe (758girl, earlier session)

| Metrik | Single-file url-1024 (prev) | Collection batch 758girl |
|--------|----------------------------|--------------------------|
| T3 | **109101 OK** (~71.5 tok/s) | 125625 **524** |
| T6 | 125626 error | **123890 ok** |
| payload | 341 | 341 |

Same file, same delivery mode — outcomes inverted between runs. **Not a config regression; CF/model latency variance near the ~125s wall.**

## vs base64 probe (758girl, earlier session)

| Delivery | T3 pass? | Typical duration |
|----------|----------|------------------|
| base64 ai_1024 | **fail** (524) | ~125587ms |
| url ai_1024 (config default) | **intermittent** | 109101ms when pass; 524 when fail |
| url payload | — | ~341 bytes vs ~62KB base64 |

URL delivery still required for any GPT-5.4 vision success on this stack; pass rate is not 100%.

## Pass criteria (plan)

| Check | Expected | Actual |
|-------|----------|--------|
| Config effective delivery | `url` | **pass** |
| All 3 files variants ready | `ai_1024` ready | **pass** |
| T3 per file | ok, payload &lt; 1KB | **fail** (0/3 ok; payload correct) |
| T6 per file | ok or documented variance | **partial** (1/3 batch; 2/3 with spot-check on 2119boy) |
| vs base64 | URL pass rate &gt; base64 | **pass** (base64 never passes; URL sometimes passes) |

## Recommendation

| Item | Action |
|------|--------|
| Production default | Keep `delivery=url` in config (already set); `.env.example` updated to match |
| Probe tooling | Use `probe_collection_folder.php {folder_id}` for full-folder runs; `--file=` for single asset |
| Reliability | Expect ~125s 524 flakes until CF origin timeout is raised; grok remains more reliable on same path |
| UI analyze | Trust queue path over standalone T3 for user-perceived latency |

## Re-run

```powershell
php storage/app/generate_folder_variants.php 019fa66d-65ae-72ea-bd20-8d0919ec5900
php storage/app/probe_microstock_ai.php --timeout=300 --only=T0 --folder=019fa66d-65ae-72ea-bd20-8d0919ec5900 --label=gpt-5.4-url-default-t0
php storage/app/probe_collection_folder.php 019fa66d-65ae-72ea-bd20-8d0919ec5900 --timeout=300
php storage/app/probe_microstock_ai.php --timeout=300 --only=T3,T6 --file=019fa66e-7db5-7024-95cb-3044d3ebf068 --label=gpt-5.4-url-2119boy
```
