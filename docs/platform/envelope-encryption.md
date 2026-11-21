# Envelope encryption & OCI KMS

Tiered crypto for sensitive columns: the KEK is the OCI KMS master key, and every value gets its own DEK (AES-256-GCM).

Sessions stay on Tier 1 (`SESSION_ENCRYPT` + `APP_KEY`). Envelope-encrypting them would add a KMS round-trip and an audit row to every request, so keep `ENCRYPTION_SESSION_ENABLED=false`. Everything genuinely sensitive is Tier 2: `EnvelopeEncryptionService` behind an `EnvelopeEncrypted*` model cast, a `DecryptedValueCache` that holds plaintext until change, explicit forget, or an optional per-`resourceType` TTL cap, and a `CryptoActivityRecorder` trail under `log_name: crypto`. The service is the **sole** decrypt choke point — nothing else unwraps a DEK. Production and Octane need a **shared** cache store (Redis); a per-worker `array` cache breaks forever semantics across processes. Crypto is audited per decrypt, so keep `ENCRYPTION_AUDIT_DECRYPTS=cache_miss`.

| | |
|---|---|
| Code | `packages/platform/src/Support/Encryption/**`, `packages/platform/src/Support/Oci/**` |
| Config | `config/encryption.php` |
| Commands | `oci:kms-verify`, `encryption:migrate-p0`, `encryption:migrate-activity-log`, `encryption:flush-decrypted-cache {context}`, `config:rebuild` |
| Tests | `composer test -- --filter=Oci` (live probe: `OCI_KMS_INTEGRATION_TEST=true … --group=oci`) |
| Pakem | [envelope-encryption.mdc](../../.cursor/rules/envelope-encryption.mdc) · [payment-gateway.mdc](../../.cursor/rules/payment-gateway.mdc) |
| Deep dive | [reference-envelope-encryption.md](reference-envelope-encryption.md) |
