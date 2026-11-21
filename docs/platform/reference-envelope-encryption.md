# Envelope encryption & OCI KMS — reference

Detail behind [envelope-encryption.md](envelope-encryption.md): tiers, components, the decrypt-cache TTL map, the P0 column inventory, env, and OCI connectivity.

## Tiers

| Tier | Mechanism | Use |
|------|-----------|-----|
| **1 — Laravel** | `SESSION_ENCRYPT` + `APP_KEY` | Sessions (every request) |
| **2 — Envelope** | `EnvelopeEncryptionService` + KMS + decrypt cache | 2FA secret, invoice payloads, attachment URLs, password-reset tokens, `2fa_token:` cache keys |

**Do not** envelope-encrypt sessions — KMS + audit noise every request. Keep `ENCRYPTION_SESSION_ENABLED=false`.

## Components

| Piece | Role |
|-------|------|
| `OciKmsClient` / `LocalKmsDekClient` | generate/unwrap DEK |
| `EnvelopeEncryptionService` | encrypt/decrypt; **sole** decrypt audit choke point; resolves per-resource TTL |
| `DecryptedValueCache` | Plaintext forever until change, explicit forget, or optional non-zero TTL cap |
| `EnvelopeEncrypted*` casts | Model integration |
| `CryptoActivityRecorder` | `log_name: crypto` |

## Billing rules

- One KEK (`OCI_KMS_KEY_ID`); many DEKs
- Decrypt cache + fingerprint; skip re-encrypt when plaintext unchanged
- No KMS retry loops on IAM failure

### Decrypt cache TTL

Aligned with [cache-policy.md](cache-policy.md): forever by default; invalidate on encrypt / cast set / flush.

| Config | Meaning |
|--------|---------|
| `encryption.decrypted_cache_max_ttl` | Optional safety cap (seconds). Default `0` = forever until forget. Non-zero still expires when configured. |
| `encryption.decrypted_cache_ttl_by_resource_type` | Per-`resourceType` override. Missing key → global. |

Current map:

| resourceType | TTL |
|--------------|-----|
| `activity_log` | `900` (audit-trail PII: rows are immutable, so plaintext in cache is bounded, never forever) |
| (all others) | global `ENCRYPTION_DECRYPTED_CACHE_MAX_TTL` (default `0`) |

Prod/Octane: use a **shared** cache store (Redis). Per-worker `array` cache breaks forever semantics across processes.

Envelope JSON v1: `v`, `alg`, `kms_key_id`, `dek_ciphertext`, `iv`, `tag`, `ciphertext`.

## P0 columns (Tier 2)

| Table | Columns |
|-------|---------|
| users | `two_factor_secret` |
| invoices | gateway request/response payloads |
| attachments | `cached_url` |
| password_reset_tokens | token + blind index |
| cache | `2fa_token:*` values only |
| activity_log | `properties` keys `ip_address`, `user_agent`, `email` (per-key selective; every other key stays plaintext) |

## Env (encryption)

| Variable | Purpose |
|----------|---------|
| `ENCRYPTION_KMS_ENABLED` | false in PHPUnit; true prod |
| `ENCRYPTION_DECRYPTED_CACHE_MAX_TTL` | default `0` (forever until forget) |
| `ENCRYPTION_AUDIT_DECRYPTS` | `cache_miss` \| `always` |
| `ENCRYPTION_BLIND_INDEX_PEPPER` | HMAC pepper |
| `ENCRYPTION_LOG_INVOICE_PAYLOAD_DECRYPTS` | optional trace log |

## OCI connectivity

Credentials live in **one folder per profile**, chosen by `APP_ENV`, not by a path in `.env`:

| `APP_ENV` | Folder | Vault |
|---|---|---|
| `production` | `.oci/production/` | production KEK |
| `local`, `staging`, `testing` | `.oci/testing/` | testing KEK |

`OciConfigPath::configFile(env('APP_ENV'))` resolves `{base}/.oci/{production|testing}/config` at config load, so `env:switch` moves the credentials with the profile — nothing is copied into a shared `.oci/config`. Each folder is self-contained: its own `config` plus `oci_api_key.pem`, with `key_file` relative so it resolves next to the config. The folder is gitignored; the vault **pair** below is not, so it is committed per profile template.

| Variable | Purpose |
|----------|---------|
| `OCI_PROFILE` | Profile section inside the config file (default `DEFAULT`) |
| `OCI_KMS_KEY_ID` | Master key OCID, per profile |
| `OCI_KMS_CRYPTO_ENDPOINT` | Vault **crypto** endpoint, per profile |
| `OCI_HTTP_TIMEOUT` | default 15s |

A config file may be the one `oci setup config` writes, as-is: OCI emits `#` comments and PHP's INI parser only accepts `;`, so `OciConfigLoader` normalizes the comment marker before parsing (and converts a parse warning into the config failure, exit `1`).

`OCI_KMS_KEY_ID` and `OCI_KMS_CRYPTO_ENDPOINT` must belong to the **same vault**. A key from one vault with another vault's crypto endpoint authenticates fine and then answers `404 NotAuthorizedOrNotFound` — the endpoint is what identifies the vault, not the key. A truncated key OCID answers `400 InvalidParameter`. Keep the pair aligned with the folder: `.oci/testing/` pairs with the testing vault, `.oci/production/` with the production one.

```bash
php artisan oci:kms-verify          # generateDek probe
php artisan encryption:migrate-p0
php artisan encryption:flush-decrypted-cache {context}
php artisan config:rebuild          # after OCI/encryption env changes
```

`context` is required: plaintext is forgotten per context, and there is no bulk flush — enumerating a cache store is driver-specific, and the cache contract is driver-agnostic. Run the command once per context suffix after a key rotation; an empty argument exits `1` and clears nothing.

Exit codes on verify: 0 ok; 1 config; 2 auth; 3 IAM; 4 network; 5 other. On 3 — fix IAM, do not loop.

## Activity log

Decrypt events: [activity-log](../domains/activity-log/README.md). Historical deep dive: [archive/crypto-activity-assessment.md](../archive/crypto-activity-assessment.md).

Audit-trail PII is itself encrypted: `activity_log.properties` keys `ip_address`, `user_agent`, `email` are envelopes written by `LogActivityAction::beforeLogging()` and decrypted in `ActivityLogResource` / the Archivd workspace feed. `activity_log` is listed in `audit_excluded_resource_types` (viewing the trail must not append `crypto` rows to it). Note the cost: `CryptoActivityRecorder` writes a `crypto` row *inside* `EnvelopeEncryptionService::decrypt()`, so each audited decrypt performs one extra DEK wrap — keep `ENCRYPTION_AUDIT_DECRYPTS=cache_miss`.

## Tests

```bash
composer test -- --filter=Oci
# optional live: OCI_KMS_INTEGRATION_TEST=true composer test -- --group=oci
```

## Related

- [envelope-encryption.md](envelope-encryption.md) — brief
- [environment.md](environment.md)
- [payment-gateway README](../domains/payment-gateway/README.md)
