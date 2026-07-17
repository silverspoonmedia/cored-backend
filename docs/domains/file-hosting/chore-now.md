# Chore: CF Worker S3 upload proxy — status

**Status:** Implemented and production-exercised (hybrid Monolith + standalone Worker). Session close-out: [2026-07-17 changelog](../../changelog/done/2026-07-17-file-hosting-hybrid-proxy-display-rename.md). Product docs: [README.md](./README.md).

| Deliverable | Location |
|-------------|----------|
| Worker (Wrangler TS) | `c:\laragon\www\workers` — Worker name **`cf-workers-s3-proxy`** |
| Public origin (example) | Custom domain e.g. `https://s3.silverspoon.me` (or `*.workers.dev`) |
| Monolith hybrid config | `config/file-hosting.php` → `upload_proxy` |
| Proxy URL builder | `App\Support\Storage\S3DirectUploadEngine::proxyUploadParts` |
| Display-name allocation | `DirectUploadService::allocateUniqueDisplayName` (on complete) |
| Client header normalize | `resources/js/file-hosting/direct-upload.js` (`putPart`) |
| Product docs | [README.md](./README.md) (Direct upload + hybrid proxy + auto-rename) |

## Agreed architecture (hybrid)

1. **Monolith** = control plane: quota, session row, `CreateMultipartUpload`, `Complete`/`Abort`, display-name allocation, promote → `HostedFile`.
2. **Browser** = PUT/UploadPart bytes only (to S3 presign **or** Worker).
3. **Optional Worker** = when `FILE_HOSTING_UPLOAD_PROXY_URL` is set, Monolith returns Worker URLs + Bearer / `X-S3-Target` instead of S3 browser presigns. Worker streams + re-signs SigV4.
4. **Part size:** default **10 MB** (`FILE_HOSTING_CHUNK_SIZE` / `FILE_HOSTING_MULTIPART_THRESHOLD`).
5. Empty proxy URL → **direct S3**. Incomplete proxy config → **fail closed** (`proxy_misconfigured`).
6. **Path-style:** Monolith `CUSTOM_S3_USE_PATH_STYLE` and Worker `forcePathStyle` must agree for B2 multipart (else `NoSuchUpload` on complete).
7. **Secrets:** Worker `S3_TARGETS` is a **runtime secret** on the deployed Worker name (`wrangler secret put … --name cf-workers-s3-proxy`). Not shipped in git or automatic with code deploy alone.

## Target alias (Monolith ↔ Worker)

| Monolith | Worker |
|----------|--------|
| `FILE_HOSTING_UPLOAD_PROXY_TARGET_S3CUSTOM` | Key under `S3_TARGETS.targets` (e.g. `sbs`) |
| Part URL path `/t/{alias}/…` | Resolved as target alias (also `X-S3-Target` header) |

Rename alias: update Worker secret **first**, then Monolith env, then `config:clear`. In-flight uploads with old alias may fail.

## Original Worker requirements (historical spec — satisfied)

Create a complete Cloudflare Worker project using Wrangler that acts as a secure, streaming multipart upload proxy for any S3-compatible storage (primarily Backblaze B2, but also other vendors). The Worker receives multipart upload requests from clients (especially from APAC) and streams each part to the target S3 endpoint without fully buffering the body in memory.

Core goal: Clients upload parts (recommended **10 MB**, max ≤ 50 MB for Free plan safety) to the nearest Cloudflare edge. The Worker re-signs the request with the correct credentials and streams the body to the remote S3-compatible service.

### Configuration Grouping (Strict Enforcement)

All configuration must be grouped under a single, clear structure called "targets" or "buckets". Do not use flat top-level secrets for every key.

Required structure (enforced):

- One Worker can serve multiple buckets and multiple vendors.
- Configuration lives in a single JSON secret or environment variable named `S3_TARGETS` (or `TARGETS_CONFIG`).
- Each target is identified by a short unique `alias` (e.g. `sbs`, `sbs-eu`, `r2-eu`).
- One credential pair (accessKeyId + secretAccessKey) can be shared across multiple buckets that use the same endpoint and credentials.
- Structure example that must be followed exactly:

```json
{
  "targets": {
    "sbs": {
      "endpoint": "https://s3.eu-central-003.backblazeb2.com",
      "region": "eu-central-003",
      "bucket": "my-primary-bucket",
      "accessKeyId": "000...keyId",
      "secretAccessKey": "K00...applicationKey",
      "forcePathStyle": true
    },
    "sbs-secondary": {
      "endpoint": "https://s3.eu-central-003.backblazeb2.com",
      "region": "eu-central-003",
      "bucket": "my-secondary-bucket",
      "accessKeyId": "000...sameKeyId",
      "secretAccessKey": "K00...sameApplicationKey",
      "forcePathStyle": true
    }
  },
  "defaultTarget": "sbs",
  "uploadToken": "strong-random-token-here"
}
```

Enforcement rules (must be implemented):
1. All targets are loaded from one secret: `S3_TARGETS` (JSON string).
2. Client must specify the target using one of these methods (in order of priority):
   - Header: `X-S3-Target: sbs`
   - Query parameter: `?target=sbs`
   - Path prefix: `/t/sbs/...` (Monolith hybrid uses this)
3. If no target is specified, use `defaultTarget`.
4. If the specified target does not exist in the config → return 400 with clear error.
5. Credentials are never accepted from the client. Only the alias is accepted.
6. The same accessKeyId + secretAccessKey pair can be reused across multiple target entries (this is the intended grouping).
7. Validate the entire config on Worker startup / first request. Reject invalid config early.
8. Never log credentials or the full config.
9. Client-facing error bodies stay **generic** in production; details only in Workers Logs.

This grouping makes one Worker able to proxy to many buckets and even different vendors (B2, Wasabi, MinIO, custom S3, etc.) without redeploying.

### Technical Requirements

- Always stream the request body using `request.body` (ReadableStream). Never call `request.arrayBuffer()`, `request.blob()`, or `request.text()` for UploadPart.
- Fully support S3 multipart operations: CreateMultipartUpload, UploadPart, CompleteMultipartUpload, AbortMultipartUpload, ListParts. Also support simple PUT for small files.
- Re-sign every request with AWS Signature Version 4 using the credentials of the selected target. Use Web Crypto API only (no heavy dependencies).
- Authentication for clients: require a shared secret via header `Authorization: Bearer <uploadToken>` or `X-Upload-Token`. Reject with 401 if missing/invalid.
- Configurable CORS (allow origins from env; preflight echoes requested headers; expose `ETag`).
- Return the exact status code, headers (especially ETag), and body from the upstream S3 service so clients can continue the multipart flow normally.
- Stateless: no Durable Objects or KV for multipart state. All state stays on the S3 side.
- TypeScript preferred. Clean modular structure.
- Minimal dependencies. Prefer zero runtime dependencies beyond Wrangler types.
- Compatible with Free plan limits (100 MB request body, 128 MB memory, 100k requests/day). Document that part size should stay ≤ 50 MB for safety.
- Production-ready error handling, proper status forwarding, and basic structured logging (never log secrets or request bodies).

### Project Structure (must follow)

- wrangler.toml (`name = "cf-workers-s3-proxy"`, placeholder for the JSON secret)
- package.json
- tsconfig.json
- src/index.ts (entrypoint)
- src/config.ts (parse and validate S3_TARGETS)
- src/signer.ts (AWS SigV4 implementation)
- src/handlers/ (create.ts, uploadPart.ts, complete.ts, abort.ts, listParts.ts, putObject.ts)
- src/utils.ts (CORS, auth check, target resolver, response helpers)
- README.md with setup, secrets (`--name cf-workers-s3-proxy`), hybrid Monolith alignment, curl examples

### Expected Behavior

1. Client authenticates with the upload token.
2. Client selects target via header/query/path (or uses default).
3. Client starts multipart on the Worker (Worker re-signs and forwards to the correct bucket/endpoint) — **or** Monolith starts multipart via server SDK (hybrid).
4. Client uploads each part to the Worker (Worker streams the part to S3).
5. Client completes or aborts via the Worker — **or** Monolith completes/aborts via server SDK (hybrid).
6. All responses look like real S3 responses so existing S3 SDKs or custom clients work with minimal changes.

*(Standalone clients may use the full Worker façade above. Monolith File Hosting uses hybrid: steps 3 and 5 on Monolith server SDK; step 4 only through the Worker when proxy is enabled.)*

### Additional Rules for the Code

- Make it easy to add new targets later just by updating the JSON secret (no code change).
- Support both path-style and virtual-hosted-style (controlled per target via forcePathStyle).
- Include a simple health/check endpoint that returns available target aliases (without credentials).
- Document Free plan limits and recommended part size clearly in README.
- Do not use R2 bindings. This is a pure external S3 proxy.
- Keep the code clean, well-commented where necessary, and ready for production use.

Generate the entire project (all files) ready to deploy with `wrangler deploy`. Include complete instructions for setting secrets and testing with `wrangler dev`.
