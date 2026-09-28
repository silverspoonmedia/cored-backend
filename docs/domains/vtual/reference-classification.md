# vTual - content classification & merge

> Part of the vTual deep dive. Siblings: [reference.md](reference.md) - identity, claims, credentials · [reference-api.md](reference-api.md) - API + SPA surfaces · [reference-ingestion.md](reference-ingestion.md) - platform clients, pools, ops.

## Content classification (music layer + cover song detection)

**Layer 1 — `vtual_contents.content_type` (`music` | `non_music`, null = unknown).** Program-only and deterministic — no AI — but **not** `categoryId` alone: vTubers routinely file covers under 20 (Gaming), 22 (People & Blogs) or 24 (Entertainment) instead of 10 (Music), so `categoryId` is one signal among several. `ContentKindClassifier` is the authority; `ContentKind::fromCategoryId` survives only as signal 2 and for legacy callers.

Signal order (first match wins), implemented in `App\Domain\Vtual\Services\ContentKindClassifier`:

1. `topicDetails.topicCategories` contains `content_classification.music_topic` (`https://en.wikipedia.org/wiki/Music`) → `music` (signal `topic`). **This is the signal that catches vTuber covers** — live probe of real covers showed categoryId 22 and 20 while all carried the Music topic, and 0/13 gameplay/ASMR/talk samples did.
2. `snippet.categoryId === content_classification.music_category_id` (`'10'`) → `music` (signal `category_id`).
3. `category_name` is in `content_classification.twitch_music_categories` → `music` (signal `category_name`). This is the **Twitch** signal: Twitch exposes neither `topicCategories` nor uploader tags, so `game_name` (the stream category) is the only music evidence a live row carries. Match is exact after case folding — a substring test would swallow "Just Chatting & Music Requests".
4. Music keyword in the **title or the uploader's tags** → `music` (signal `keyword`). Never the description — gaming videos credit background music. Latin keywords match on a word boundary (`cover` must not fire on "discover"/"coverage"); CJK keywords are plain substrings.
5. Description credits an original (`本家` / `原曲` / `original`) **and** links a video (`youtu.be` / `youtube.com` / `nicovideo.jp` / `nico.ms`) → `music` (signal `original_credit`).
6. No positive signal and duration ≥ `long_form_min_seconds` (600) → `non_music` (signal `long_form`).
7. No positive signal, short or unknown duration → **null (unknown)**. The AI audit decides these; guessing `non_music` here is the false negative this layer exists to remove.
8. No signal at all (e.g. a Twitch stream in a non-music category) → null.

`topicDetails` **must stay in `config('vtual.youtube.default_video_parts')`** — dropping it silently degrades the layer to `categoryId`-only. `PlatformVideoDto` carries `categoryId`, `topicCategories`, `tags` and `channelTitle` (mapped in `YoutubeDataApiClient::mapVideo`; Twitch passes empty arrays / null) and `PlatformVideoDtoMapper` forwards all of them plus `duration_seconds` into the upsert payload, so `ContentService::resolveKind` (the fallback for hand-built payloads) reaches the same verdict. `channelTitle` is what the sub-category layer's catalog artist test compares against. Exposed on the public profile API and the probe preview.

**Twitch live rows carry `category_name` instead.** `PlatformVideoDtoMapper::toUpsertPayloadFromStream` forwards `game_name` as `category_name` (signal 3 above) and, when it matches `content_classification.twitch_music_categories`, also as a single derived entry in `tags` so the sub-category layer reads the same evidence. A **non-music** category contributes no tag: a derived label would be indistinguishable from an uploader-authored one, and the tag vocabulary (`tag_cover` carries `cover`, `tag_singing_stream` carries `karaoke stream`) would then mislabel a gaming stream that merely names a song. `ContentKindClassifier::hasMusicCategory()` is public precisely so the mapper derives tags from one list instead of two.

**Layer 2 — `vtual_contents.content_category` (`cover` | `original` | `singing_stream`, nullable = unclassified sub-category) + `content_category_detail` (catalog attribution, e.g. `てにをは - ヴィラン`).** Sub-classification inside the music layer, set inline during video hydrate by `ContentCategoryClassifier` (via `PlatformVideoDtoMapper`), recomputed every hydrate (idempotent). Never throws — failure leaves null. Best-effort: unknown sub-category does not affect `content_type`. `ContentCategoryClassifier::evaluate()` returns `{category, detail, signal, catalog}` — `signal` names the deciding rule (`original_keyword` | `singing_stream` | `strong_cover` | `original_provide` | `tags` | `cover_provenance` | `weak_cover` | `catalog_artist_match` | `catalog_artist_mismatch` | `none`) and `catalog` the iTunes label the catalog branch used. `classify()` (drop the signal) and `classifyStored()` remain as thin wrappers.

Decision order (first match wins):

1. **Original** — `オリジナル曲` / `オリジナル楽曲` / `オリジナルMV` / `original song|mv` in title.
2. **Singing stream** — `歌枠` / `カラオケ枠` / `singing stream` / `karaoke stream` in title (unambiguous regardless of type).
3. **Cover (strong)** — `歌ってみた`, `covered by`, `cover by`, bracket-adjacent `cover】|】cover`, `弾き語り`, plus the parenthesised labels `(cover)` / `（カバー）`.
4. **Original (composer credit)** — `提供した楽曲` / `提供曲` in title/description (`兎迷夢々さんに提供した楽曲です`). The uploader wrote the song for another singer, so this video is the original even though the catalog entry carries the performer's name. Checked *after* the title labels: a cover described as "〇〇様の提供曲を歌ってみた" keeps its cover verdict.
5. **Uploader tags** — the uploader's own `snippet.tags` (`歌ってみた` / `cover` → cover, `オリジナル曲` / `original song` → original, `歌枠` / `カラオケ` → singing stream). Original wins when both are present. On Twitch this step also sees the mapper's derived music-category tag.
6. **Cover (provenance)** — description contains `本家` / `原曲` **and** links a music page (`youtu.be` / `youtube.com` / `nicovideo.jp` / `nico.ms`) → cover (`本家様：https://youtu.be/…` is the standard cover provenance block). The English word `original` is deliberately **not** a provenance marker — an original MV's own description says "Original MV" next to a YouTube link.
7. **Cover (weak → verify)** — `cover` / `カバー` in title+description **and** corroborated by YouTube `snippet.categoryId=10` (Music) or duration 90–420 s → lookup via `ItunesSearchClient` for attribution.
8. **Catalog artist test** — `snippet.channelTitle` vs the iTunes catalog artist for the cleaned title term (needs the same corroboration gate). Equal → **original**; different → **cover** with the catalog label as attribution; no match/channel name → null. This is what resolves keyword-free videos such as `【GUMI】KING【Kanaria】` without the AI audit.
9. Otherwise null.

**Vocabulary is deliberately narrow at step 7.** `feat.`, `acoustic` and a bare `歌` were removed: `DECO*27 - モニタリング (Best Friend Remix) feat. 初音ミク` and `ジャバウォック / wotaku feat. 初音ミク` are originals that were labelled covers by those entries, and `歌` matches 歌おう / この歌は / 歌詞 in any description. Cover-**art** credits (`Cover Art:`, `Illustration:`, `artwork`, `jacket`) are stripped from title and description before matching, because an original MV credits its illustrator.

**Catalog lookup specifics:** `ItunesSearchClient` ranks candidates by how much of the track title the query covers (≥ 0.75), drops track titles that mark themselves as cover releases (`(cover)` / `カバー` / `self cover`), and can prefer a candidate whose artist matches a hint (the channel title). The title term is stripped of decorations **and** of the cover label (`magnet (minato/流星P) - くろくも cover` → `magnet - くろくも`); attribution additionally considers the leading song segment (`プロポーズ/ 9Lana` → `プロポーズ`), because the uploader's name in the query can push the composer's release off the result page. Attribution prefers a candidate the description actually credits (`Lyrics & Music：内緒のピアス`) over the best title match, and never reports the uploader's own release as the original. Keyless public API, cached per term 7 h, failure-safe. **Bounded limit, not open work:** a vTuber who publishes a cover to streaming services under their own name can satisfy the catalog artist match — the explicit title/tag/provenance rules run first, and when they abstain the AI audit receives the catalogue verdict as evidence.

Config: `config/vtual.php` → `content_classification` (`music_category_id`, `music_topic`, `twitch_music_categories`, `long_form_min_seconds`, `original_credit_markers`, `cover_provenance_markers`, `original_provide_markers`, `cover_decoy_phrases`, `cover_paren_keywords`, `tag_cover` / `tag_original` / `tag_singing_stream`, `cover_duration`, `itunes.*` incl. `artist_disambiguation`, `ai.*`). YouTube `categoryId` / `topicDetails` / `tags` / `channelTitle` flow through `PlatformVideoDto` (no extra quota — already inside the `videos.list` response).

**AI audit pass (both layers, asynchronously):** the program pass deliberately leaves ambiguous short-form videos unknown, so an LLM audit resolves them — it may set **either** layer (`content_type` *and* the sub-category). Where the program pass produced a verdict, that verdict stays authoritative. Config split follows the repo tier policy: endpoint + API key come from the core [Ai domain](../ai/README.md) — bound through the Tier B slug `vtual.content_classification.ai.connection` (empty = the default `ai_connections` row), while `vtual.content_classification.ai.*` (enabled, model, max_tokens, timeout, min_confidence, cache_ttl_hours) stay Tier B — Settings → vTual. Disabled/unconfigured by default outside local; the local profile turns it on with `vtual.content_classification.ai.enabled = true` + `model = internal.sspn_misc` ([`config/settings-local.php`](../../../config/settings-local.php)). Failure, malformed JSON, a missing `is_music` flag, or low confidence all return null and leave the row untouched (never throws, cached per external_id — **including negative verdicts**, so the pool does not re-ask). The former `VTUAL_AI_CLASSIFY_ENDPOINT` / `VTUAL_AI_CLASSIFY_API_KEY` env keys are removed.

**Reply parsing is defensive.** Chat-completions endpoints wrap the object in a markdown fence (```` ```json … ``` ````) or pad it with prose even under `response_format: json_object`; `ContentAiClassifier::decodeJson()` decodes the raw body first, then the outermost `{…}` object. **`max_tokens` must cover reasoning tokens** for reasoning-style aliases — at a low budget the model spends everything before emitting `content`, and the reply arrives as `finish_reason: length` with an empty body. Measured on real videos: 600 returned empty content on 4 of 5, 4000 worked, so the default is 4000 (floor 256) and the local profile matches with `timeout` 120.

Audit paths:
- **Hydrate:** `ContentService::upsertFromPrimary` → `ContentAiAuditService::enqueueIfEligible` records a pending row in `vtual_content_ai_audit` when `content_type` is null **or** `content_type = music` with a null sub-category (only when AI enabled + configured; no-op otherwise, never blocks hydrate). Idempotent per content while a pending row exists.
- **Drainer:** `php artisan vtual:audit-content-ai [--limit=25] [--max-attempts=3] [--stale-minutes=15]` claims pending rows (`lockForUpdate`), calls the AI, writes both layers, and marks `done` / `failed` with `attempts`, `error`, `processed_at`. Scheduled every five minutes with `--limit=5` (one measured call ran 7–40 s, so the batch must fit inside the window) and `withoutOverlapping`. **This is the piece that makes the pool real** — without it the table is write-only. A run killed mid-flight leaves rows in `processing`; `--stale-minutes` returns them to `pending`, and `attempts` is charged at claim time so retries stay bounded.
- **Manual pass:** `php artisan vtual:classify-contents --ai` runs the program pass over YouTube rows and then audits whatever is still incomplete (fails fast with a clear error when AI is unconfigured).

Backfill stored rows (program pass, **no AI**): `php artisan vtual:classify-contents [--chunk=500] [--include-classified] [--refresh] [--ai]` — YouTube rows with `hydrated_at` set and `content_category` **or** `content_type` null. The stored row carries no `categoryId` / `topicCategories` (transient mapper inputs), so a backfill works from title / description / duration / **channel name** / **stored `tags`** — the catalog artist test still runs (cached), so keyword-free originals can resolve without the AI audit. The default run never overwrites an existing verdict, so after a classifier change use **`--refresh`**: it recomputes both program layers over the whole set, rewrites the program-managed columns, and clears a sub-category the current rules no longer justify. A cleared row falls back to the AI-audit bucket rather than keeping an unexplainable label — pair `--refresh --ai` in one run to refill those rows immediately, because the audit queue is only fed on hydrate. Public API `GET vtual/public/vtubers/{slug}` contents items expose `content_type` + `content_category` + `content_category_detail`.

**`vtual_contents.tags` (nullable JSON, internal).** Uploader `snippet.tags` are a first-class classification signal (Layer 1 step 3, Layer 2 step 5) that exists **only** on the platform payload, so hydrate persists them: `PlatformVideoDtoMapper` forwards `tags` → `ContentService::resolveTags` trims, drops non-strings/blanks, de-duplicates, keeps the first 40, and stores an empty list as `null` (same convention as `resolveRegionList` — "no tags" and "tags cleared upstream" must look identical). A later hydrate without tags clears the column, so a stale label can never drive a classification. **Never expose tags in `ContentResource` / `PublicContentResource` / `PublicVtuberProfileResource`** — they are a signal, not presentation copy, and leaking them publishes the raw platform payload key by key (`VtualPublicApiTest` asserts absence). Read back by `ClassifyContentsCommand` and `AuditContentAiCommand`.

**AI evidence contract (`ContentAiVerdictApplier` + prompt).** The AI audit is the fallback for the layers the program pass **could not** decide, never a second opinion on the layers it did. That contract is enforced twice, deliberately:

- **On the write** — `ContentAiVerdictApplier::apply()` is the only path that stamps an AI verdict onto a row, shared by `vtual:audit-content-ai` and `vtual:classify-contents --ai` so the two cannot drift. `content_type` is written only while null (a model "not music" answer never erases a keyword-backed music verdict); `content_category` is written only while null; `content_category_detail` is written only when it describes the sub-category the row actually carries (attribution text for a different verdict is dropped). A verdict the program pass already owned is closed out rather than retried — a bound verdict is a final answer. Both commands report `ai_ignored` for those rows instead of counting the raw model answer.
- **In the prompt** — `ContentAiClassifier::systemPrompt()` states the same rule to the model, and `userPrompt()` renders the program verdict as an explicit evidence block: `content type`, `sub-category`, `attribution already found`, `deciding signal` and `iTunes catalogue match` are emitted **only for layers the program pass decided** (a null layer produces no line — printing "unknown" would read as a finding and invite the model to echo it). `channel_title` and the stored `tags` ride along. The catalogue match is flagged as the strongest available original-vs-cover signal: equal artist → original, different artist → cover.

A prompt that promises a contract the code does not keep is worse than no prompt at all, which is why the two halves ship together.

## Tests

```bash
composer test -- --filter=Vtual
composer test -- --filter=PlatformCredential
composer test -- --filter=AccessibilityBlade
```

| Suite | Coverage |
|-------|----------|
| `SlugGeneratorTest` | Slugify + unique suffix |
| `CreatorMemberRoleTest` | Mutate / manage members matrix |
| `CreatorServiceTest` | Multi-creator ownership, slug collision, archive |
| `ManagementServiceTest` | Owner membership, assign/unassign `left_at` |
| `ChannelFetchPoolServiceTest` | Pool threshold/age/force flush; coalesce; single HTTP; apply snapshot |
| `ChannelServiceTest` | `seedForStaff` (manage any), non-staff denied, `activateFromDto`, refresh enqueues pool, archive/restore, pending not archivable, `deletePending` force-delete, WebSub kept on archive, restore resub from unsubscribed, lifecycle cooldown + burst 429 |
| `CreatorAccessServiceTest` | `memberRoleFor` ignores staff override; `roleFor` still pretends staff is Owner |
| `ChannelClaimServiceTest` | Start pending only; verify bio token; Accept Anyway; taken external_id blocked |
| `ChannelProfileInputParserTest` / `ChannelProfileResolverTest` | Parse matrix YT/Twitch; handle → canonical id via Http::fake |
| `PlatformCredentialSecretsTest` | Payload normalize / fingerprint / reject unknown keys |
| `PlatformCredentialServiceTest` | pending create, pickActive, Test YT/Twitch, getAppAccessToken reuse/remint, secret rotate reset, forever decrypt cache |
| `ContentServiceTest` | Primary host + network appearance, late-join no auto-attach, listForChannel/Creator, `listForPublicProfile` excludes archived-channel content, detach/setPrimary invariants |
| `CreatorMergeServiceTest` | Absorb channels/links; clash 422; non-owner 403; staff orphans; pending claims `merged` |
| `CreatorClaimServiceTest` | Orphan create, claimable list, bio verify Http::fake, cooldown, Accept Anyway, already-owned (no attempt burn), winner supersedes other pending, max_pending_per_user, staff listPending |
| `PlatformApiGatewayTest` / `BatchIdChunkerTest` | Manual HTTP clients: multi-id, batch max, parts, auth, 401 markFailed + ErrorLog + soft failover (no secrets) |
| `PlatformCredentialApiTest` | Masked CRUD + Test, RBAC, Root/Admin OK, User/Moderator 403, validation 422 |
| `DecryptedValueCacheTest` | vtual resource TTL 0; default global `0` forever; non-zero global TTL still expires when configured |
| `VtualApiTest` | CRUD API, user channel claim path, staff-as-owner claim (not pending), staff orphan pending, delete pending, archive pending 422, product gate, peer isolation |
| `RegisterVtualActivationTest` | Register creates implicit Vtual activation |
| `VtualLivePredicateTest` | Appearance-aware `is_live`: appearance-only live creator live on profile + directory sort + guest management roster; false when the appearance's channel is archived |
| `PromoteDiscoveredChannelTest` | Isolated promote: already-public / not-discovered / disabled-platform / Kick rejections + non-member 403 |
| `ChannelStubContentBackfillServiceTest` | Stub hydrate on restore; skip paths |
| `VtuberProfileApiTest` | Public profile API; archived channel hides contents; restore + backfill shows contents |
| `VtualPublicApiTest` | Guest directory / contents feed / profile: archived+private hidden, live-first sort, pagination, no UUID, channel `banner_url`, subscriber totals; profile grid filters — `platform`, `type`, `sort=oldest` (no live pinning) vs default, unknown values clamp to `null`/`newest` (200, never 422), visibility rules (archived primary channel, `unavailable`) hold under a filter, appearance-only content still returned under a filter |
| `PublicManagementProfileApiTest` | Guest management profile: roster limited to publicly listed creators, roster `is_live` (true on live content, false when the live row's primary channel is archived), aggregate `stats` (incl. zeroed stats for an empty roster) |
| `VtuberProfileServiceTest` | `show` excludes archived-channel content; all-archived → 0 contents |
| `ChannelFeedPollServiceTest` | Watermark enqueue, initial backfill guard; archived channel stub upsert (no pool enqueue); archived included in due poll |
| `ManagementEventsApiTest` | Management event submit + **update**: owner create/update + realtime, staff 403, ended 422, cross-management 404, blank/bad-date validation 422, single-event read RBAC, landing cache flush |
| `ContentIngestionPoolTest` | Feed poll + content pool + live CCV integration; Twitch live branch classifies a music-category stream as music and derives its tag, a non-music category derives none |
| `DiscordConnectionDiscoveryServiceTest` | Private channel bind, promote, external links from Discord snapshot |
| `DiscordConnectionsClientTest` | Normalize all connection types; `ownershipEligible`; `externalLinkCandidates` |
| `ContentKindClassifierTest` | Music layer signal hierarchy — Music topic, categoryId, Twitch category name (case-folded exact match, configurable list, non-music stays unknown), title/tag keyword, original credit, long-form, unknown bucket |
| `ContentCategoryClassifierTest` | Sub-category decision order + regressions from the live corpus: `feat.`-only original not a cover, cover-art decoy, `本家`+link provenance, `original` + link never cover, composer 提供 credit, tag labels, catalog artist match/mismatch, per-branch `signal` |
| `ItunesSearchClientTest` | Candidate ranking by title overlap, cover-release exclusion, artist-hint preference, per-term cache, failure/disabled/empty-term safety, credit matching |
| `YoutubeVideoSignalsMappingTest` | `topicDetails` in default parts; `topicCategories`/`tags`/`channelTitle` mapped onto the DTO and probe serializer |
| `ContentAiClassifierTest` | Fence-tolerant JSON parse, both-layer verdict shape, `is_music` false / missing, signal prompt, program-verdict + catalogue evidence block, abstaining verdict emits no evidence lines, `max_tokens` wiring / floor, low-confidence |
| `ContentAiVerdictApplierTest` | Program verdict binds on write — null row takes the whole verdict, bound music/sub-category survives a disagreeing answer, attribution only for the row's own sub-category, not-music clears a stale sub-category only where it stands, no-op verdict is not a change |
| `ContentAiAuditServiceTest` | Audit enqueue: unknown kind, music without sub-category, skip resolved/non-music, no-op when disabled, no duplicates |
| `AuditContentAiCommandTest` | `vtual:audit-content-ai` drain: writes both layers, clears stale sub-category on a non-music verdict, a program music verdict binds against a disagreeing model, stored tags + channel ride into the prompt, failed/stale/attempt-cap handling |
| `ContentTagsPersistenceTest` | `tags` DTO → upsert payload → row: trim/dedupe/cap-40, empty list stored as null, rehydrate clears stale tags |
| `ClassifyContentsCommandTest` | `vtual:classify-contents` program + `--ai` passes; `--refresh` recompute/clear + catalog check with the channel name; applier refuses a verdict the program pass already owns |
| `CreatorClaimServiceTest` (filters) | Bio proof-channel-only; Discord discovery; `verified_channel_id` contract |
| `CreatorClaimApiTest` | Claims hub: `{items, pagination}` envelope on `mine` + `pending`, `actions[]` gating, staff RBAC |
| `VtualClaimQueryRowActionsTest` | Row-action set per status / actor; nothing on a closed claim |
| `VtualClaimsStaffRealtimeTest` | Staff ping payload: ids + action + status, never a snapshot or token |
| `VtualClaimsChannelTest` | `private-vtual.claims` channel auth (staff allowed, plain user denied) |
| `PlatformHttpRetryTest` | 429 → success, quota-exhaustion message, `Retry-After`, 4xx not retried |
| `ContentRegionRestrictionTest` | `regionRestriction` → DTO → row → public payload; empty → null |
| `ManagementAuditTrailTest` | `vtual` log rows per management mutation; invite tokens absent |
| ~~`VtuberProfileWebTest`, `PlatformCredentialWebTest`, `CreatorProfileManageWebTest`, `VtualBladeWebTest`, `AccessibilityBladeTest`~~ | **Deleted with the Blade UI** — the SPA carries those screens and `tests/_archive/` no longer exists. Behavior is covered by the API feature tests above plus the SPA Vitest suite |


## Profile merge (absorb) — implemented

One User may own many Creators. **Merge** absorbs source into destination (same owner on both, or staff `manage any`).

| Step | Behavior |
|------|----------|
| Auth | Owner membership on **both** profiles, or `manage any vtual resource` |
| Move | Channels, external links, contents + appearances `creator_id`, management assignments |
| Cancel | Pending creator claims + channel claims on source → `cancelled` + `closed_reason=merged` |
| Keep | Destination `display_name` / `slug` / `bio` / `avatar`; verified claim audit rows on source |
| Conflict | Same `(platform_id, external_id)` on both → 422 (no partial move) |
| Source after | **Archived** (`status=archived`), members stripped, slug mangled to `merged-{slug}-{suffix}` so it is **not claimable** |

**Do not** leave source active without members (would become claimable empty shell). Soft-delete / hard-delete not used.

API: `POST /api/v1/vtual/creators/{source}/merge` body `{ "destination_id": "uuid" }`.  
SPA: manage profile (`/apps/vtual/profiles/{id}`) → Merge card → pick a destination (owner + ≥2 targets).

Closed — out of scope: field pick from source, cross-user merge without dual ownership, single-channel reparent-only API, undo. Absorb is one-way and keeps the destination's meta; an undo needs a tombstone/ledger design and a field-selection UI, and no product requirement has asked for it. Reopen with a spec, not a ticket.


## Related

- [archive/vtual-vue-island-assessment.md](../../archive/vtual-vue-island-assessment.md) — Vue island promotion analysis (2026-08-09)
- [implementation-status.md](implementation-status.md) — living progress tracker: every row Implemented or Decided (no open work)
- [archive/vtual-youtube-stability-assessment-2026-08-07.md](../../archive/vtual-youtube-stability-assessment-2026-08-07.md) — YouTube ops probe rubric (2026-08-07)
- [naming-conventions.md](naming-conventions.md) — core `Management` vs talent org (`vtual_managements`)
- [product-activations/README.md](../product-activations/README.md) — runtime gate
- [archivd/README.md](../archivd/README.md) — sibling product domain pattern
- [identity/README.md](../identity/README.md) — User hub, product RBAC domain_kind
- [platform/architecture.md](../../platform/architecture.md) — domain registry
- [archive/vtual-legacy-audit.md](../../archive/vtual-legacy-audit.md) — broadercast claim ledger
