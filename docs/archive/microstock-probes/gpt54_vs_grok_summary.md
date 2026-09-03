# GPT-5.4 vs Grok — Open WebUI Probe Comparison

**Date:** 2026-07-28  
**Probe label:** `gpt-5.4-openwebui`  
**Report:** `gpt-5.4-openwebui_20260728_012201.json`  
**Asset:** `Group_2119boy.png` (folder `019fa5f6-9675-73a0-b7b2-e77c9cd2b934`)  
**Path:** Open WebUI → 9Router combo `vision` → `gpt-5.4`

## Summary

| Test | Grok baseline (Open WebUI, Jul 2026) | GPT-5.4 (this probe) | Delta |
|------|--------------------------------------|----------------------|-------|
| T0 text-only | ~6000 ms, OK | 5318 ms, OK | ~12% faster |
| T0 resolved_model | `grok-grok-4.5-high` | `gpt-5.4` | combo switch confirmed |
| T0 tokens/sec | (not recorded) | 92.89 | — |
| T3 vision 1024px | ~46000 ms, OK | 125574 ms, **HTTP 524** | **failed** — CF origin timeout |
| T3 tokens/sec | (not recorded) | n/a | — |
| T6 full `generate()` | ~50000 ms, OK | 125744 ms, error | vision stage failed (524) |

## Interpretation

1. **Combo routing works** — T0 `resolved_model` is `gpt-5.4`, not grok. The 9Router `vision` backend change propagated through Open WebUI.
2. **No latency improvement on vision** — T3 did not complete. HTTP **524** at ~125 s is Cloudflare cutting the connection before Open WebUI/origin returns. Grok on the same path completed T3 in ~46 s. GPT-5.4 multimodal vision is **slower or stalls** past the CF ~125 s window for this payload.
3. **T6 failure is downstream of T3** — `generate()` blocks on vision; vision 524 → `AI metadata generation failed.`
4. **Text-only path is fine** — T0 at 5.3 s with ~93 tokens/sec shows the model and gateway are alive; the problem is **vision + json_object + 1024px** duration, not a dead upstream.

## Recommendations

| Action | Why |
|--------|-----|
| Revert `vision` combo to `grok-grok-4.5-high` for production microstock | Grok passed T3/T6 on Open WebUI; GPT-5.4 did not |
| Or raise CF/origin timeout above 125 s | Only if you insist on GPT-5.4 for vision — risky, may still be slow |
| Or lower `MICROSTOCK_AI_IMAGE_MAX_PX` (e.g. 768) and re-probe | Smaller image may finish under 125 s |
| Keep `gpt-5.4` in `ai_billing.models` | Billing is correct if you use it elsewhere; rate $2.50/$15 per M |

## Re-run

```powershell
php storage/app/probe_microstock_ai.php --timeout=300 --label=gpt-5.4-openwebui --folder=019fa5f6-9675-73a0-b7b2-e77c9cd2b934
```
