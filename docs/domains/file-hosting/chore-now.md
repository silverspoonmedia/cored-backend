Create a complete Cloudflare Worker project using Wrangler that acts as a secure, streaming multipart upload proxy for any S3-compatible storage (primarily Backblaze B2, but also other vendors). The Worker receives multipart upload requests from clients (especially from APAC) and streams each part to the target S3 endpoint without fully buffering the body in memory.

Core goal: Clients upload parts (recommended 10-50 MB) to the nearest Cloudflare edge. The Worker re-signs the request with the correct credentials and streams the body to the remote S3-compatible service.

### Configuration Grouping (Strict Enforcement)

All configuration must be grouped under a single, clear structure called "targets" or "buckets". Do not use flat top-level secrets for every key.

Required structure (enforced):

- One Worker can serve multiple buckets and multiple vendors.
- Configuration lives in a single JSON secret or environment variable named `S3_TARGETS` (or `TARGETS_CONFIG`).
- Each target is identified by a short unique `alias` (e.g. "b2-main", "b2-backup", "r2-eu").
- One credential pair (accessKeyId + secretAccessKey) can be shared across multiple buckets that use the same endpoint and credentials.
- Structure example that must be followed exactly:

```json
{
  "targets": {
    "b2-main": {
      "endpoint": "https://s3.eu-central-003.backblazeb2.com",
      "region": "eu-central-003",
      "bucket": "my-primary-bucket",
      "accessKeyId": "000...keyId",
      "secretAccessKey": "K00...applicationKey",
      "forcePathStyle": true
    },
    "b2-secondary": {
      "endpoint": "https://s3.eu-central-003.backblazeb2.com",
      "region": "eu-central-003",
      "bucket": "my-secondary-bucket",
      "accessKeyId": "000...sameKeyId",
      "secretAccessKey": "K00...sameApplicationKey",
      "forcePathStyle": true
    }
  },
  "defaultTarget": "b2-main",
  "uploadToken": "strong-random-token-here"
}
```

Enforcement rules (must be implemented):
1. All targets are loaded from one secret: `S3_TARGETS` (JSON string).
2. Client must specify the target using one of these methods (in order of priority):
   - Header: `X-S3-Target: b2-main`
   - Query parameter: `?target=b2-main`
   - Path prefix: `/t/b2-main/...` (optional, if clean path is preferred)
3. If no target is specified, use `defaultTarget`.
4. If the specified target does not exist in the config → return 400 with clear error.
5. Credentials are never accepted from the client. Only the alias is accepted.
6. The same accessKeyId + secretAccessKey pair can be reused across multiple target entries (this is the intended grouping).
7. Validate the entire config on Worker startup / first request. Reject invalid config early.
8. Never log credentials or the full config.

This grouping makes one Worker able to proxy to many buckets and even different vendors (B2, Wasabi, MinIO, custom S3, etc.) without redeploying.

### Technical Requirements

- Always stream the request body using `request.body` (ReadableStream). Never call `request.arrayBuffer()`, `request.blob()`, or `request.text()` for UploadPart.
- Fully support S3 multipart operations: CreateMultipartUpload, UploadPart, CompleteMultipartUpload, AbortMultipartUpload, ListParts. Also support simple PUT for small files.
- Re-sign every request with AWS Signature Version 4 using the credentials of the selected target. Use Web Crypto API only (no heavy dependencies).
- Authentication for clients: require a shared secret via header `Authorization: Bearer <uploadToken>` or `X-Upload-Token`. Reject with 401 if missing/invalid.
- Configurable CORS (allow origins from env or hardcode sensible defaults + OPTIONS preflight).
- Return the exact status code, headers (especially ETag), and body from the upstream S3 service so clients can continue the multipart flow normally.
- Stateless: no Durable Objects or KV for multipart state. All state stays on the S3 side.
- TypeScript preferred. Clean modular structure.
- Minimal dependencies. Prefer zero runtime dependencies beyond Wrangler types.
- Compatible with Free plan limits (100 MB request body, 128 MB memory, 100k requests/day). Document that part size should stay ≤ 50 MB for safety.
- Production-ready error handling, proper status forwarding, and basic structured logging (never log secrets or request bodies).

### Project Structure (must follow)

- wrangler.toml (with placeholder for the JSON secret)
- package.json
- tsconfig.json
- src/index.ts (entrypoint)
- src/config.ts (parse and validate S3_TARGETS)
- src/signer.ts (AWS SigV4 implementation)
- src/handlers/ (create.ts, uploadPart.ts, complete.ts, abort.ts, listParts.ts, putObject.ts)
- src/utils.ts (CORS, auth check, target resolver, response helpers)
- README.md with clear setup, how to set the S3_TARGETS secret, example client flow, and curl examples for full multipart upload.

### Expected Behavior

1. Client authenticates with the upload token.
2. Client selects target via header/query (or uses default).
3. Client starts multipart on the Worker (Worker re-signs and forwards to the correct bucket/endpoint).
4. Client uploads each part to the Worker (Worker streams the part to S3).
5. Client completes or aborts via the Worker.
6. All responses look like real S3 responses so existing S3 SDKs or custom clients work with minimal changes.

### Additional Rules for the Code

- Make it easy to add new targets later just by updating the JSON secret (no code change).
- Support both path-style and virtual-hosted-style (controlled per target via forcePathStyle).
- Include a simple health/check endpoint that returns available target aliases (without credentials).
- Document Free plan limits and recommended part size clearly in README.
- Do not use R2 bindings. This is a pure external S3 proxy.
- Keep the code clean, well-commented where necessary, and ready for production use.

Generate the entire project (all files) ready to deploy with `wrangler deploy`. Include complete instructions for setting secrets and testing with `wrangler dev`.