# Crypto activity & envelope encryption assessment

Security and audit assessment for envelope encryption (OCI KMS) and `log_name: crypto` activity entries. Complements [activity-log.md](./activity-log.md) and [envelope-encryption.md](./envelope-encryption.md).

**Last reviewed:** 2026-07-12

---

## Executive summary

| Question | Verdict |
|----------|---------|
| Is `kms_key_id` in logs safe? | **Yes** — OCID resource identifier, not key material. Risk is infrastructure fingerprinting if logs leak outside `view activity log`. |
| Is the crypto audit trail complete? | **Partial** — decrypt metadata is recorded; encrypt/migrate events are not. Repeat decrypts after cache invalidation are invisible unless `ENCRYPTION_AUDIT_DECRYPTS=always`. |
| Is envelope encryption secure? | **Sound design** (KEK/DEK, AES-256-GCM, blind index, single decrypt choke point). **Not fully production-hardened** without ops checklist below. |
| Is caching implemented? | **Yes** — invalidate-on-change plaintext decrypt cache (24h safety cap, ciphertext fingerprint) and envelope-at-rest for `2fa_token:` cache keys. |
| Production ready? | **Production-ready** — deploy after checklist; monitor KMS billing and cache security. |

---

## Scope

### What is audited

- **Decrypt only** via `CryptoActivityRecorder` (`event: field_decrypted`, `log_name: crypto`).
- Triggered from `EnvelopeEncryptionService::decrypt()` — the sole audited decrypt path for P0 columns.
- **Never logged:** plaintext, ciphertext, DEK, wrapped DEK, IV, or tag.

### P0 surfaces covered (Tier 2 envelope)

| Surface | `resource_type` | Typical `field` |
|---------|-----------------|-----------------|
| `users.two_factor_secret` | `users` | `two_factor_secret` |
| Invoice gateway payloads | `invoices` | `gateway_*_payload` |
| Attachment presigned URLs | `attachments` | `cached_url` |
| 2FA pending token cache | `cache` | `value` (`2fa_token:` keys) |

**Sessions are not envelope-encrypted** — they use Laravel `SESSION_ENCRYPT` (APP_KEY). Crypto audit rows for `resource_type: sessions` are excluded by config (`audit_excluded_resource_types` / `audit_excluded_purposes`) as a safety net if the legacy `encrypted_database` handler is re-enabled.

### Who can view crypto logs

API routes require `permission:view activity log` (Root + Admin by default). Full `properties` JSON is stored in `activity_log`; the API **masks** `kms_key_id` for crypto rows (see [API masking](#api-masking)).

---

## `kms_key_id` analysis

The value logged is the **OCID of the KMS master key** copied into envelope JSON at encrypt time (`config('oci.kms_key_id')` or `'local'` in tests). It is **not** a secret that enables decryption.

| | |
|--|--|
| **Why keep it in DB** | Key-rotation forensics — which KEK wrapped the DEK when this row was written. |
| **Risk if exposed** | Attacker learns which vault/key you use; does **not** bypass KMS IAM. |
| **Product decision** | Store full OCID in `activity_log.properties`; mask in `ActivityLogResource` API responses. |

---

## What “complete” crypto audit means

| Dimension | Recorded today | Gap |
|-----------|----------------|-----|
| **WHO** | `causer` (JWT user when present) | Missing on CLI, queue, or unauthenticated decrypts |
| **WHAT** | `resource_type`, `resource_id`, `field` | No Spatie `subject` morph (flat properties only) |
| **WHEN** | `created_at` | — |
| **WHERE** | `ip_address` | No `user_agent` / request id |
| **WHY** | `purpose` | — |
| **HOW (key)** | `kms_key_id` (full in DB, masked in API) | — |
| **Envelope context** | `envelope_version`, `legacy_ciphertext`, `cache_hit` (when applicable) | Encrypt events not logged |
| **Repeat reads** | Only if `ENCRYPTION_AUDIT_DECRYPTS=always` | Default `cache_miss` skips repeat decrypts until cache invalidation |

---

## Caching semantics

### Decrypted plaintext cache (`DecryptedValueCache`)

- **Stores:** Plaintext after successful KMS unwrap + AES decrypt.
- **Invalidation:** `forgetByContext()` on encrypt / cast `set()` when plaintext changes; ciphertext fingerprint mismatch also forces miss.
- **Key:** `encryption.decrypted.{resourceType}.{field}.{resourceId}.{ciphertextHash}` with pointer `encryption.decrypted.pointer.{context}`.
- **Safety cap:** `ENCRYPTION_DECRYPTED_CACHE_MAX_TTL` (default **86400** seconds; `0` = forever until forget). `ENCRYPTION_DECRYPTED_CACHE_TTL` is a deprecated alias.
- **Store:** `CACHE_STORE` (Redis in production per [octane-readiness.md](./octane-readiness.md)).
- **Tradeoff:** Fewer KMS calls vs plaintext in shared cache — compromise of Redis exposes secrets without KMS.
- **Invoice note:** Identical gateway payloads on sync no longer re-encrypt (cast skip); cache survives beyond the old 900s TTL when ciphertext is stable.

### Sensitive cache envelope (`SensitiveCacheValue`)

- **Stores:** Envelope JSON (ciphertext) for `2fa_token:` keys — not plaintext.
- **TTL:** 300s (hardcoded in `AuthService`).

### Audit vs cache

| `ENCRYPTION_AUDIT_DECRYPTS` | Behavior |
|-----------------------------|----------|
| `cache_miss` (default) | Log first decrypt per context + ciphertext fingerprint until invalidation. |
| `always` | Log every decrypt; `cache_hit: true` when served from decrypt cache. |

---

## API masking

`ActivityLogResource` passes crypto `properties` through `CryptoActivityPropertyPresenter::forApi()`:

- `kms_key_id` OCIDs are truncated for display (prefix + `...` + suffix).
- Database rows retain the full OCID for forensics.

---

## Production readiness checklist

Before enabling envelope encryption in production:

1. `php artisan oci:kms-verify` → exit **0**
2. Set `ENCRYPTION_KMS_ENABLED=true`
3. Set `ENCRYPTION_BLIND_INDEX_PEPPER` to a long random stable value (never empty in prod)
4. `php artisan config:rebuild` after OCI / encryption env changes
5. Run `php artisan encryption:migrate-p0` on existing data
6. Choose `ENCRYPTION_DECRYPTED_CACHE_MAX_TTL` vs KMS cost vs plaintext retention risk
7. Choose `ENCRYPTION_AUDIT_DECRYPTS` (`cache_miss` vs `always`) for compliance needs
8. Plan Redis key purge for incident response (`encryption:flush-decrypted-cache {context}` — bulk flush not implemented)
9. Optional: enable `ENCRYPTION_LOG_INVOICE_PAYLOAD_DECRYPTS` temporarily to trace invoice payload decrypt sources — see [envelope-encryption.md](./envelope-encryption.md#invoice-payload-decrypt-tracing)

---

## Known limitations (deferred)

| Item | Notes |
|------|-------|
| `DecryptedValueCache::flushAll()` | No-op; bulk `encryption:flush-decrypted-cache` without context exits with error — Redis SCAN purge deferred |
| Session envelope (legacy) | Disabled by default; use `SESSION_ENCRYPT` instead — see [envelope-encryption.md](./envelope-encryption.md#tiered-encryption) |
| No encrypt audit | Writes and `encryption:migrate-p0` do not create crypto log rows |
| `decryptWithoutCache()` | Public, unaudited — only safe when called from audited paths |
| Legacy pass-through | Non-envelope values may return as-is during migration |
| P1 PII columns | Blind-index infrastructure only; column migrations deferred |

---

## Related docs

- [envelope-encryption.md](./envelope-encryption.md) — architecture and commands
- [oci-kms.md](./oci-kms.md) — KMS connectivity
- [activity-log.md](./activity-log.md) — log namespaces and API
