# Envelope encryption & OCI KMS

Tiered crypto for sensitive columns. KEK = OCI KMS master key; DEK per value (AES-256-GCM).

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

Aligned with [cache-policy.md](../../platform/cache-policy.md): forever by default; invalidate on encrypt / cast set / flush.

| Config | Meaning |
|--------|---------|
| `encryption.decrypted_cache_max_ttl` | Optional safety cap (seconds). Default `0` = forever until forget. Non-zero still expires when configured. |
| `encryption.decrypted_cache_ttl_by_resource_type` | Per-`resourceType` override. Missing key → global. |

Current map:

| resourceType | TTL |
|--------------|-----|
| `vtual_platform_credentials` | `0` (forever until secret/runtime rewrite / `markFailed` / flush) |
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
| vtual_platform_credentials | `secret_payload`, `runtime_payload` (purpose `vtual`; forever decrypt cache; runtime = system tokens e.g. Twitch app access) |

## Env (encryption)

| Variable | Purpose |
|----------|---------|
| `ENCRYPTION_KMS_ENABLED` | false in PHPUnit; true prod |
| `ENCRYPTION_DECRYPTED_CACHE_MAX_TTL` | default `0` (forever until forget) |
| `ENCRYPTION_AUDIT_DECRYPTS` | `cache_miss` \| `always` |
| `ENCRYPTION_BLIND_INDEX_PEPPER` | HMAC pepper |
| `ENCRYPTION_LOG_INVOICE_PAYLOAD_DECRYPTS` | optional trace log |

## OCI connectivity

| Variable | Purpose |
|----------|---------|
| `OCI_CONFIG_FILE`, `OCI_PROFILE` | SDK config (Windows: forward slashes) |
| `OCI_KMS_KEY_ID` | Master key OCID |
| `OCI_KMS_CRYPTO_ENDPOINT` | Vault **crypto** endpoint |
| `OCI_HTTP_TIMEOUT` | default 15s |

```bash
php artisan oci:kms-verify          # generateDek probe
php artisan encryption:migrate-p0
php artisan encryption:flush-decrypted-cache {context?}
php artisan config:rebuild          # after OCI/encryption env changes
```

Exit codes on verify: 0 ok; 1 config; 2 auth; 3 IAM; 4 network; 5 other. On 3 — fix IAM, do not loop.

## Activity log

Decrypt events: [activity-log](../activity-log/README.md). Historical deep dive: [archive/crypto-activity-assessment.md](../../archive/crypto-activity-assessment.md).

## Tests

```bash
composer test -- --filter=Oci
# optional live: OCI_KMS_INTEGRATION_TEST=true composer test -- --group=oci
```

## Related

- [platform/environment.md](../../platform/environment.md)
- [payment-gateway README](README.md)
