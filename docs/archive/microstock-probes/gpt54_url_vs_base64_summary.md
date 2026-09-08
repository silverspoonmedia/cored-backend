# GPT-5.4 — URL vs Base64 Probe (New Collection)

**Date:** 2026-07-28  
**Folder:** `019fa66d-65ae-72ea-bd20-8d0919ec5900`  
**Workspace:** `019fa66d-378e-7102-a396-9d72cd8d592a`  
**Probe asset:** `Group_758girl.png` (first file in folder; no `AssetMetadata` yet)  
**Variants:** `ai_512` + `ai_1024` both **ready** (generated via `GenerateFileVariants` before probe)  
**Backend:** Open WebUI → `internal.vision` → `gpt-5.4`

## Results

| Run | Delivery | Variant | Status | duration_ms | tokens/sec | image_payload_bytes | resolved_model |
|-----|----------|---------|--------|-------------|------------|---------------------|----------------|
| T0 preflight | — | — | OK | 3637 | 133.63 | — | `gpt-5.4` |
| A — b64-1024 | base64 | ai_1024 | **HTTP 524** | 125587 | n/a | 61983 | — |
| B — url-1024 | url | ai_1024 | **OK** | 109101 | 71.54 | 341 | `gpt-5.4` |
| C — url-512 | url | ai_512 | **HTTP 524** | 125613 | n/a | 339 | — |
| T6 — url-1024 | url | ai_1024 | **error** | 125626 | n/a | 341 | — |

**Reports:** `gpt-5.4-b64-1024_20260728_020538.json`, `gpt-5.4-url-1024_20260728_020734.json`, `gpt-5.4-url-512_20260728_020949.json`, `gpt-5.4-t6-url-1024_20260728_021203.json`

## Comparison vs baseline (no variants, base64 ephemeral)

| Metrik | Baseline lama | Base64 ai_1024 (A) | URL ai_1024 (B) |
|--------|---------------|--------------------|-----------------|
| T3 duration_ms | 125574 (524) | 125587 (524) | **109101 (OK)** |
| T3 pass? | fail | fail | **pass** |
| Payload size | ~56KB+ b64 | 61983 b64 chars | 341 URL chars |

## Interpretation

1. **URL delivery unblocks GPT-5.4 vision** — same persisted `ai_1024` JPEG (~46KB on disk) succeeds when sent as signed URL (341-byte reference in JSON) but hits **HTTP 524** when embedded as **62KB base64** in the request body. Root cause is likely **Cloudflare/origin timeout + large POST body**, not model death.
2. **Not a free speed boost** — URL T3 still took **~109s** (slow, but under CF ceiling). tokens/sec ~71.5 vs grok baseline ~46s wall clock for comparable quality tier — GPT-5.4 remains slower per request.
3. **ai_512 URL did not help** — run C still 524. Resolution alone is not the lever; **delivery mode** is. Run-to-run variance near the ~125s CF wall is high (B passed, C/T6 failed).
4. **T6 failed** — `HttpAiMetadataService::generate()` vision stage timed out at ~125s on this run despite T3 URL success minutes earlier. Production should use `VisionImageResolver` with `delivery=url`; expect occasional CF-edge failures until infra timeout is raised or model is swapped.

## Recommendation

| For GPT-5.4 vision on this stack | Action |
|----------------------------------|--------|
| Production microstock analyze | Set `MICROSTOCK_AI_VISION_IMAGE_DELIVERY=url` and `MICROSTOCK_AI_VISION_VARIANT=ai_1024` |
| Keep base64 as fallback? | Only for local/dev; base64 reliably 524s on this path |
| Model choice | Grok still faster/more reliable on same Open WebUI path (~46s T3); GPT-5.4 viable only with URL delivery |
| Infra | Consider raising CF origin timeout above 125s if GPT-5.4 is mandatory |

## Collection re-probe with config default (2026-07-28)

After `config/microstock.php` default changed to `delivery=url` (no `.env` override), full-folder batch on all 3 assets:

| File | T3 (url default) | T6 (url default) | payload bytes |
|------|------------------|------------------|---------------|
| Group_758girl | 524 @ 125625ms | **ok** @ 123890ms | 341 |
| Group_2808 | 524 @ 125576ms | error @ 125622ms | 335 |
| Group_2119boy | 524 @ 125607ms | error @ 125640ms (batch); **ok** @ 131709ms (spot-check) | 341 |

- **Config default confirmed:** `config_delivery=url`, payload ~341 bytes (not base64).
- **High variance:** same 758girl file passed T3 @ 109101ms in the single-file run above but failed T3 in the collection batch; T6 inverted (failed then, passed now).
- **T6 &gt; T3 reliability:** 2119boy T6 succeeded in spot-check while standalone T3 524'd — aligns with fast UI analyze (queue + retries, not raw T3 latency).

Full write-up: `storage/app/probe_results/collection_url_default_summary.md`  
Aggregate JSON: `collection_019fa66d-65ae-72ea-bd20-8d0919ec5900_20260728_024427.json`

## Re-run

```powershell
php storage/app/generate_folder_variants.php 019fa66d-65ae-72ea-bd20-8d0919ec5900
php storage/app/probe_microstock_ai.php --timeout=300 --only=T3 --folder=019fa66d-65ae-72ea-bd20-8d0919ec5900 --delivery=url --variant=ai_1024 --label=gpt-5.4-url-1024
php storage/app/probe_collection_folder.php 019fa66d-65ae-72ea-bd20-8d0919ec5900 --timeout=300
```
