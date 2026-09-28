# Microstock — marketplace upload (decision record)

Decision record for FTP/API auto-upload, the item `reference-api.md` used to list under *Explicitly out of scope (MVP)* and now documents as shipped. Code: `app/Domain/Microstock/Services/MarketplaceUploadService.php`, `app/Domain/Microstock/Integrations/…/SftpMarketplaceTransport.php`, `app/Domain/Microstock/Jobs/UploadCollectionToMarketplace.php`, `app/Domain/Microstock/Models/MarketplaceUpload.php`.

The pipeline's last manual step is the marketplace contributor portal — picking the files, pasting the metadata. An **opt-in marketplace upload** (Adobe Stock over SFTP) now automates that drop without replacing the CSV: it consumes the envelope-encrypted credential the table already stored. See [reference-marketplace-upload.md](reference-marketplace-upload.md).

## Decisions

| # | Question | Decision |
|---|----------|----------|
| D1 | Which marketplace first? | **Adobe Stock**, over its published SFTP contributor endpoint. |
| D2 | Which protocol? | **SFTP**, implemented in-process with `phpseclib/phpseclib` (pure PHP). Not FTP, not a REST API, not a sidecar. |
| D3 | Does upload replace CSV export? | **No.** The CSV stays the primary, always-available path. Upload is an additional, opt-in action on a `review`/`exported` collection. |
| D4 | Field mapping | Per-marketplace, config-driven, in `config/microstock.php` → `upload.marketplaces.*.columns`. Generic CSV columns stay the default. |
| D5 | File retention after upload | **Keep everything.** No source file, derivative, metadata row or CSV is ever deleted or moved by an upload. |
| D6 | Who may upload? | The actor who may mutate the collection (`WorkspaceAccessService::assertCanMutate`), using a credential **they own**. |

## D1 — Why Adobe Stock

- **The protocol is published and stable.** Adobe Stock documents an SFTP drop with a companion CSV; the shape does not change per account tier.
- **The metadata we already produce is the metadata it wants.** Title, keywords and a category are exactly the three fields the pipeline finalizes — no new extraction stage.
- **A second marketplace is a mapping, not a rewrite.** Shutterstock and Pond5 speak the same FTP-plus-CSV shape with different column names and category vocabularies, so D4's config table is the only per-marketplace artifact. Shipping Adobe first proves the transport without pretending we support N marketplaces.
- **Rejected alternative: REST.** Adobe's contributor REST surface is account-management oriented, not a bulk asset drop; using it would mean a different auth flow and a different failure model for no gain over SFTP.

## D2 — Why SFTP via phpseclib

- **`ext-ssh2` is not available on this stack.** The Windows/Laragon PHP CLI build ships without the extension (same class of gap as the missing `pcntl`/`posix` documented in `reference-api.md`), and requiring it would make the feature undeployable on the development machine and on any host that does not compile it in.
- **phpseclib is pure PHP**, so the same code path runs locally, in tests and in production with no extension matrix. It is also the library the ecosystem already uses for this, and it needs no daemon.
- **FTP is rejected**: Adobe's plain-FTP endpoint sends credentials in clear text, and we would be storing a password specifically to leak it.
- **A sidecar/Worker is rejected**: it moves credentials out of the envelope-encrypted store into a second system, for a transfer that is one blocking call inside an existing job runner.

## D3 — CSV stays primary

Upload is additive by design, for four reasons: the CSV path has no external dependency and therefore cannot be the thing that breaks; a collection may be destined for a marketplace we do not support yet; a contributor account may not be configured; and the export is also the review artifact a human reads. `POST collections/{folder}/upload` is a **new** endpoint that requires an existing export state — it never replaces `export`/`download`.

## D4 — Field mapping

`microstock.csv.columns` (default `filename,title,description,category,keywords`) remains the generic export. Upload resolves a **marketplace profile** first, and falls back to the generic columns when the profile has none:

| Marketplace key | Metadata payload | File payload | Notes |
|-----------------|------------------|--------------|-------|
| `adobe_stock` | CSV: `Filename`, `Title`, `Keywords`, `Category` | original file, filename unchanged | `Category` is Adobe's **numeric** category id; the id↔name table lives in config so it is reviewable, not buried in a service. Keywords join with `, ` inside one quoted cell. |

Rules that hold for every profile:

- **The remote filename is the local filename.** Marketplaces match CSV rows to files by name, so a rename would silently detach the metadata. Names are validated before the transfer (`[A-Za-z0-9._-]`, non-empty, unique within the batch) and the upload fails the whole batch rather than sending a half-matched set.
- **Only `finalized` metadata is uploaded** — the same rule the CSV export follows. A `generated` row is a draft.
- **A missing required field fails that row's file, not the batch.** Adobe rejects a file without a title; we refuse to send it and report it, so the owner fixes the row and re-uploads instead of getting a marketplace-side error nobody can trace.
- **Config, not code.** A new marketplace is a new entry under `upload.marketplaces`, never a new `if`.

## D5 — Retention

An upload is a **copy out**, not a move:

- Source files, `ai_512` / `ai_1024` derivatives, `AssetMetadata` rows and the exported CSV all stay exactly where they are. Deleting them would make re-upload to a second marketplace impossible and would break the Archivd quota accounting that already counts those bytes.
- What is recorded is the **attempt**: marketplace, credential, collection, status, remote path, file count, byte count, error, timestamps. That row is the audit trail and the UI status.
- Credentials are retained until the owner deletes them, as today. A failed upload never deletes or rotates a credential.
- **No remote cleanup.** We never delete anything at the marketplace, even after a re-upload — the marketplace owns its library, and a delete endpoint is a destructive capability this feature does not need.

## D6 — Authorization

Upload is gated by `WorkspaceAccessService::assertCanMutate($workspace, $actor)` — the same gate as lock/analyze/export, so a member who can already drive the collection can ship it. The credential must belong to the **acting user**: a shared workspace does not share credentials, and the endpoint returns 404 for a credential id the actor does not own (same shape as `CredentialService::delete`). Every secret stays in the envelope-encrypted `secret_payload`; the upload never writes plaintext back to a column, a log, or a response.

## Explicitly out of scope

- **No scheduled or automatic upload.** An upload is an explicit user action on one collection; a marketplace drop that happens on its own is a different feature with a different consent story.
- **No marketplace-side status polling.** We report *our* transfer result, not whether the marketplace accepted the asset into review.
- **No per-file partial retry UI.** A failed batch is re-run as a batch; the row-level errors name the files.
- **No keychain/agent auth, no SSH host-key pinning UI.** The credential's host key is verified against the provider's published fingerprint; there is no trust-on-first-use prompt.
- **No Shutterstock/Pond5 profile until Adobe ships.** The config shape is ready; an empty profile is not a feature.

## Status

**Decided — implemented** (2026-09-27). The table below is the shipped shape; the API contract and its test list live in [reference-api.md](reference-api.md).

| Piece | Where |
|-------|-------|
| Transport | `MarketplaceUploadService` over `phpseclib/phpseclib` (`^3.0`), one SFTP connection per batch. |
| Job | `UploadCollectionToMarketplace` — explicit `tries` / `backoff` / `timeout`, on a dedicated queue key added to `WorkerQueues::ORDER`. |
| Endpoint | `POST /api/v1/microstock/collections/{folder}/upload` (`credential_id` in the body), gated like the other collection actions. |
| Credential columns | `status` (`pending` \| `verified` \| `failed`), `last_error`, `last_used_at`, `last_verified_at` on `microstock_marketplace_credentials`. |
| Attempt record | `microstock_marketplace_uploads` — owner, collection, credential, marketplace, status, remote path, file/byte counts, error, timestamps. |
| SPA | Upload action + status column on the collection toolbar and the credentials view; 7 locales. |
| Tests | `MarketplaceUploadServiceTest` (SFTP faked — no network), `UploadCollectionToMarketplaceTest`, API test. |
