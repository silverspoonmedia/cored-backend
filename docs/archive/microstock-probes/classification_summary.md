# Grok-4.5 Matrix Classification Summary

**Date:** 2026-07-27  
**Primary class:** **A — Slow but healthy**

## Key finding (invalidates prior "hang" diagnosis)

The earlier probe used **120s client timeout**. Production T3 (1024px vision + json_object) took **111,585ms** — succeeds within **300s** but fails at **120s** with `cURL 28: 0 bytes received`. That is **client timeout**, not upstream hang.

9Router dashboard (809 requests / 73.6M input tokens in 24h) is consistent with **active, repeated dev traffic** — not a dead model.

## Matrix results (timeout=300s)

| Test | Duration | Status | Notes |
|------|----------|--------|-------|
| T0 text-only | 6.3s | ok | Combo responds |
| T1 vision prompt, no image | 43.8s | ok | |
| T2 vision + 64px | 28.5s | ok | |
| T3 vision + 1024px + json_object | **111.6s** | ok | Near old 120s probe limit |
| T4 vision + 1024px, no json_object | 52.1s | ok | Faster than T3 |
| T5 vision + HTTPS signed URL | 40.5s | ok | URL works; faster than base64 |
| T6 full generate() | 56.5s | ok | vision + thinking complete |
| T7 generate(), thinking text-only | 60.6s | ok | Not materially faster than T6 |

## Token economics

- Per `generate()`: 1 vision + up to 2 multimodal thinking + 2 text thinking = **5 HTTP calls** max
- With `http_retries=1`: up to **10 attempts** per asset worst case
- 809 requests / ~5 ≈ **162 asset-runs** — plausible for re-analyze sessions
- 65% cached input tokens = repeated prompts/images

## Recommendations

1. **Do not label grok as "hang"** — use `microstock.ai.stage` `duration_ms` instead
2. Keep `MICROSTOCK_AI_VISION_TIMEOUT=300` (or higher for 1024px)
3. Optional: signed HTTPS URL for vision (T5 faster than T3) — not required for correctness
4. `json_object` adds latency on grok (T3 vs T4) but T3 still succeeds — optional future config
5. `thinking_visual_attempts=2` default retained — T7 not faster than T6
