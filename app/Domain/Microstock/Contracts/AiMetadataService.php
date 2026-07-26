<?php

namespace App\Domain\Microstock\Contracts;

/**
 * AI metadata generator. Stage 1 (vision) drafts title/keywords from the asset;
 * stage 2 (thinking) curates them. Swappable — tests bind a stub.
 */
interface AiMetadataService
{
    /**
     * Analyze an asset image and return curated metadata.
     *
     * @param  string  $imageUrl  Publicly reachable (signed) URL of the asset.
     * @return array{title: string, keywords: list<string>, raw: array<string, mixed>}
     */
    public function generate(string $imageUrl): array;
}
