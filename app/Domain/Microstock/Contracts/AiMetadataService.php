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
     * @param  string|null  $hint  Optional owner description of the whole
     *                             collection, used to ground the metadata.
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: array<string, mixed>, stage_metrics: list<array<string, mixed>>}
     */
    public function generate(string $imageUrl, ?string $hint = null): array;

    /**
     * Analyze many assets in a single AI request. The provider receives every
     * image plus its id and must return a JSON array of per-file metadata.
     *
     * @param  array<int, array{id: string, image: string}>  $items  Each item carries
     *                                                               the metadata id and a downscaled data-URI preview.
     * @param  string|null  $hint  Optional owner description of the collection.
     * @return array<string, array{title: string, keywords: list<string>, description: string, category: string, raw: array<string, mixed>}>
     *                                                                                                                  Map of metadata id => metadata. May omit ids the AI skipped.
     */
    public function generateBatch(array $items, ?string $hint = null): array;

    /**
     * Curate a vision draft (stage 2). When a preview data-URI is provided the
     * thinking model verifies the draft against the image before curating.
     *
     * @param  list<string>  $keywords
     * @return array{title: string, keywords: list<string>, description: string, category: string, raw: mixed, stage_metrics: list<array<string, mixed>>}
     */
    public function curateDraft(
        string $title,
        array $keywords,
        ?string $hint = null,
        string $description = '',
        string $category = '',
        ?string $imageUrl = null,
    ): array;
}
