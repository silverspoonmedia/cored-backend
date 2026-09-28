# vTual — third-party embed policy (Twitch player / chat)

Decision record for the public-profile embed question (`implementation-status.md` — "Twitch player / chat embed on the public profile"). Code today: `app/Domain/Vtual/Http/Resources/PublicVtuberProfileResource.php`, `app/Http/Middleware/SecurityHeaders.php`, `monolith-vue-workers/public/_headers`.

The guest profile shows a content grid whose rows are links. A row click leaves the SPA for the platform (YouTube watch page, Twitch channel). The open question was whether a Twitch row should instead play **inside** the profile — an iframe player, and optionally the chat — the way the old Blade profile did.

## The blocking decision

**Does the public profile embed the platform player, or link out to it?**

| Option | Semantics | Consequence |
|--------|-----------|-------------|
| **A — Link out** *(decided)* | The grid stays a grid of links. The profile is a catalogue; playback is the platform's job. | Zero third-party frame surface, no cookie/consent surface, no CSP widening. The guest loses in-page playback. |
| **B — Embed** | Twitch player (and chat) render in an iframe on the profile. | Requires a CSP `frame-src` widening on **two** surfaces, a cookie/consent answer for the embed's trackers, a per-platform embed matrix (YouTube oEmbed vs. Twitch `player.twitch.tv` vs. Kick — no API at all), and an autoplay/fallback story for a region-blocked row. |

Recommendation and final call: **A**. This is a product decision, not an engineering gap — the code to embed is small; the policy surface it opens is not.

## Why A

- **The CSP is deny-by-default on frames, in two places, deliberately.** Monolith `SecurityHeaders::CSP_DIRECTIVES` ships `frame-src https://hcaptcha.com https://*.hcaptcha.com`; the SPA's `public/_headers` ships the same value. hCaptcha is the only frame allowed, and the middleware comment says why. Embedding Twitch means adding `https://player.twitch.tv` + `https://www.twitch.tv` to `frame-src` on both, which turns a two-value allowlist into a per-platform list that must grow with every platform we ingest.
- **A third-party frame is a tracking surface.** The Twitch embed sets its own cookies and reads its own storage in the guest's browser, on a public page that currently has no consent surface and no cookie banner. Answering that is a privacy/consent project, not a profile tweak.
- **The embed matrix is not one embed.** YouTube already serves an oEmbed thumbnail path; Twitch needs `player.twitch.tv/?channel=&parent=` with a **`parent` parameter that must match the embedding host** (so per-environment configuration, and a broken embed when the host changes); Kick has no public API at all (see the `Kick` row in `implementation-status.md`). A single "embed" switch would ship for one platform and silently not exist for the others.
- **The catalogue promise does not need it.** The profile answers "who is this vTuber and what have they published"; the platform answers "play it". Region-restricted rows already carry `region_allowed` / `region_blocked` hints and the card shows a notice rather than hiding the row — an embed would make a withheld video look like a broken player instead.
- **Cost of reversal is low, cost of shipping is not.** Deciding A keeps the link-out contract (`url` on every content item, `platform_key` on every row). Reopening B later is a spec plus a CSP change; shipping B now would put the CSP, the consent question and the `parent`-host config in the critical path of a guest page that currently has none of those problems.

## Explicitly out of scope

- **No player iframe on the profile.** Not for Twitch, not for YouTube, not behind a toggle.
- **No chat embed.** Same frame and consent surface, plus a second WebSocket origin.
- **No CSP relaxation.** `frame-src` keeps hCaptcha as its only value on both surfaces; a future embed decision owns that edit.
- **No autoplay or in-page playback fallback.** The link is the playback path.

## Status

**Decided: A (link out) — final.** Not "deferred": the question is answered, and reopening it means writing a spec that names the platform, the consent surface, and the `parent`-host configuration — not filing a ticket.

| Piece | Where it stands |
|-------|-----------------|
| Content rows | `url` + `platform_key` on every item in `PublicVtuberProfileResource`; the SPA card opens the link. |
| CSP (backend) | `app/Http/Middleware/SecurityHeaders.php` — `frame-src https://hcaptcha.com https://*.hcaptcha.com`. |
| CSP (SPA) | `monolith-vue-workers/public/_headers` — same `frame-src` value. |
| Guest profile | `VkStr1VtuberShowView.vue` — content grid, chip filters, no iframe. |
