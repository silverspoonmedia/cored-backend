<?php

namespace App\Support\Encryption;

use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class EnvelopeEncryptionService
{
    private const ALGORITHM = 'AES-256-GCM';

    public function __construct(
        private readonly KmsDekClientInterface $kmsClient,
        private readonly DecryptedValueCache $decryptedValueCache,
        private readonly CryptoActivityRecorder $cryptoActivityRecorder,
    ) {}

    public function encrypt(string $plaintext, ?DecryptionContext $context = null): string
    {
        if ($context !== null) {
            $this->decryptedValueCache->forget($context->cacheKey());
        }

        $dekResult = $this->kmsClient->generateDek();

        if (! $dekResult->successful || $dekResult->plaintextDek === null || $dekResult->wrappedDek === null) {
            throw new RuntimeException($dekResult->message ?? 'Failed to generate a data encryption key.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $dekResult->plaintextDek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM encryption failed.');
        }

        $envelope = new EncryptedEnvelope(
            version: 1,
            algorithm: self::ALGORITHM,
            kmsKeyId: (string) config('oci.kms_key_id', 'local'),
            dekCiphertext: $dekResult->wrappedDek,
            iv: base64_encode($iv),
            tag: base64_encode($tag),
            ciphertext: base64_encode($ciphertext),
        );

        return $envelope->toJson();
    }

    public function decrypt(string $storedValue, DecryptionContext $context): string
    {
        return $this->decryptedValueCache->remember($context->cacheKey(), function () use ($storedValue, $context): string {
            $plaintext = $this->decryptWithoutCache($storedValue);

            $this->cryptoActivityRecorder->recordFieldDecrypted(
                resourceType: $context->resourceType,
                resourceId: $context->resourceId,
                field: $context->field,
                purpose: $context->purpose,
                kmsKeyId: $this->resolveKmsKeyId($storedValue),
            );

            return $plaintext;
        });
    }

    public function decryptWithoutCache(string $storedValue): string
    {
        if (EncryptedEnvelope::isEnvelopeJson($storedValue)) {
            return $this->decryptEnvelope($storedValue);
        }

        return $this->decryptLegacyValue($storedValue);
    }

    public function migrateLegacyValue(string $storedValue): string
    {
        if (EncryptedEnvelope::isEnvelopeJson($storedValue)) {
            return $storedValue;
        }

        $plaintext = $this->decryptLegacyValue($storedValue);

        return $this->encrypt($plaintext);
    }

    public function decryptedValueCacheForget(DecryptionContext $context): void
    {
        $this->decryptedValueCache->forget($context->cacheKey());
    }

    private function decryptEnvelope(string $storedValue): string
    {
        $envelope = EncryptedEnvelope::fromJson($storedValue);

        $dekResult = $this->kmsClient->decryptDek($envelope->dekCiphertext);

        if (! $dekResult->successful || $dekResult->plaintextDek === null) {
            throw new RuntimeException($dekResult->message ?? 'Failed to unwrap the data encryption key.');
        }

        $iv = base64_decode($envelope->iv, true);
        $tag = base64_decode($envelope->tag, true);
        $ciphertext = base64_decode($envelope->ciphertext, true);

        if ($iv === false || $tag === false || $ciphertext === false) {
            throw new RuntimeException('Envelope payload contains invalid base64.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $dekResult->plaintextDek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('AES-256-GCM decryption failed.');
        }

        return $plaintext;
    }

    private function decryptLegacyValue(string $storedValue): string
    {
        try {
            return Crypt::decryptString($storedValue);
        } catch (\Throwable) {
            return $storedValue;
        }
    }

    private function resolveKmsKeyId(string $storedValue): ?string
    {
        if (! EncryptedEnvelope::isEnvelopeJson($storedValue)) {
            return null;
        }

        return EncryptedEnvelope::fromJson($storedValue)->kmsKeyId;
    }
}
