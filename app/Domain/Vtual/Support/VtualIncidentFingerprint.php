<?php

namespace App\Domain\Vtual\Support;

use App\Domain\ErrorLog\Support\ErrorMessageNormalizer;

/**
 * Stable fingerprint for grouping incident occurrences.
 *
 * Shape: `sha1(kind|subject|normalized_title|normalized_detail)`.
 *
 * **Fingerprint stability is a contract.** A repeated sync error must collapse
 * into one row so the occurrence count means "this is still broken", not "this
 * happened 400 times". Changing the parts — or the normalization — splits every
 * existing group, so treat it as a migration, not a tweak.
 *
 * The **subject** (`platform|channel|credential` ids) is part of the key on
 * purpose: the same "subscription missing" failure on two different channels is
 * two incidents, because they are fixed one channel at a time. Ids are
 * normalized away *inside* the message text (they are the volatile part there),
 * but the explicit subject ids are what make the group actionable.
 *
 * **Severity is deliberately NOT a part.** The same failure escalating from
 * `warning` to `critical` is the same incident getting worse, not a second one;
 * the upsert refreshes severity on each occurrence, and a resolve can then look
 * the row up without having to guess how loud the failure was.
 */
class VtualIncidentFingerprint
{
    public function __construct(
        private readonly ErrorMessageNormalizer $normalizer,
    ) {}

    public function make(
        string $kind,
        ?string $platformId,
        ?string $channelId,
        ?string $credentialId,
        string $title,
        ?string $detail,
    ): string {
        $subject = implode(':', [
            $platformId ?? '',
            $channelId ?? '',
            $credentialId ?? '',
        ]);

        $parts = [
            strtolower($kind),
            $subject,
            $this->normalizer->normalize($title),
            $this->normalizer->normalize($detail),
        ];

        return hash('sha1', implode('|', $parts));
    }
}
