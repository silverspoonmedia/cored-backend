# Backend Compare Summary

**Captured:** 2026-07-27T22:57:09+00:00
**Timeout:** 300s
**Collection:** 019fa42d-3b38-716a-a302-d7249685de5a — hint: "Children doing sports"

## Latency (ms)

| Test | direct_9router | open_webui |
|------|----------------|------------|
| T0 | ok 5460ms / 3161 tok | ok 6432ms / 3135 tok |
| T3 | ok 44713ms / 8016 tok | ok 45896ms / 7824 tok |
| T6 | ok 54485ms | ok 50096ms |

## Quality rubric (per asset)

### Group_2119boy.png

| Backend | Status | Duration | Title | KW | Rubric |
|---------|--------|----------|-------|----|--------|
| direct_9router | ok | 59765 | Cartoon boy running in sportswear for sport exerci | 22 | pass |
| open_webui | ok | 40253 | Smiling cartoon boy running in sportswear and snea | 23 | pass |

### Group_2808.png

| Backend | Status | Duration | Title | KW | Rubric |
|---------|--------|----------|-------|----|--------|
| direct_9router | ok | 128675 | Cartoon girl with ponytail running in sportswear | 23 | pass |
| open_webui | ok | 55495 | Cartoon girl with ponytail running in sports activ | 23 | pass |

### Group_758girl.png

| Backend | Status | Duration | Title | KW | Rubric |
|---------|--------|----------|-------|----|--------|
| direct_9router | ok | 56135 | Cartoon girl running during kids sports activity | 23 | pass |
| open_webui | ok | 53093 | Smiling cartoon girl running in kids sports illust | 24 | pass |

## Recommendation

**Keep Open WebUI** — comparable latency and quality; human-focused routing via `internal.*` aliases.

## Findings (2026-07-27 session)

### Same upstream model

Both paths resolved to **`grok-grok-4.5-high`** in response bodies. Open WebUI is a different API surface (`/api/chat/completions`, `internal.*` aliases), not a different model.

### Latency

| Metric | direct_9router | open_webui | Delta |
|--------|----------------|------------|-------|
| T0 | 5.5s | 6.4s | ~same |
| T3 (1024px vision) | 44.7s | 45.9s | ~same |
| T6 (full generate) | 54.5s | 50.1s | Open WebUI **~8% faster** |
| Quality total (3 assets) | 244.6s | 148.8s | Open WebUI **~39% faster** |

Group_2808.png was the outlier: direct 9Router took **129s** vs Open WebUI **55s**.

### Response shape

- **direct 9Router** vision (T3) often wraps JSON in markdown fences (` ```json ... ``` `) — `HttpAiMetadataService::parseMetadataJson()` strips these.
- **Open WebUI** returned pretty-printed JSON without fences in T3 preview — same parser path, cleaner upstream output.

### Metadata quality (collection 019fa42d, hint: "Children doing sports")

| Asset | direct title | open_webui title | Gender |
|-------|--------------|------------------|--------|
| Group_2119boy.png | Cartoon boy running in sportswear for sport exercise | **Smiling** cartoon boy running in sportswear and sneakers | boy ✓ |
| Group_2808.png | Cartoon girl with ponytail running in sportswear | Cartoon girl with ponytail running in sports activity | girl ✓ |
| Group_758girl.png | Cartoon girl running during kids sports activity | **Smiling** cartoon girl running in kids sports illustration | girl ✓ |

- All 6 runs passed rubric (15–30 keywords, title 40–70 chars, no BAN hits, no soccer hallucination).
- Open WebUI titles read slightly more natural ("Smiling cartoon …") — aligns with Postman observation of more human-focused tone.
- Keyword sets nearly identical; both ground in running/sport, not invented equipment.

### E2E pipeline (Open WebUI, `verify_e2e_asset.php`)

- Status: **generated**
- Stage sequence: vision start → vision end (**43.6s**) → thinking start → thinking end (**8.5s**)
- `ai_raw.thinking` present

### Decision

**Keep `MICROSTOCK_AI_*` on Open WebUI** (`ai-chat.spnd.uk`). Use `MICROSTOCK_PROBE_9ROUTER_*` only when re-running `probe_backend_compare.php`.
