<?php

namespace App\Domain\Vtual\Integrations\Youtube;

use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\YoutubeFeedVideoEntryDto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SimpleXMLElement;

/**
 * Parse YouTube channel video Atom feeds (public feed + WebSub push).
 */
class YoutubeAtomFeedParser
{
    /**
     * @return Collection<int, YoutubeFeedVideoEntryDto>
     */
    public function parseEntries(string $xml, bool $throwOnInvalid = false): Collection
    {
        $root = $this->loadRoot($xml);
        if (! $root instanceof SimpleXMLElement) {
            if ($throwOnInvalid && trim($xml) !== '') {
                throw new VtualException(__('api/vtual.integrations.feed_parse_failed'), 502);
            }

            return collect();
        }

        $root->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $root->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');

        /** @var list<SimpleXMLElement> $entries */
        $entries = $root->xpath('//atom:entry') ?: [];

        return collect($entries)
            ->map(fn (SimpleXMLElement $entry): ?YoutubeFeedVideoEntryDto => $this->mapEntry($entry))
            ->filter(fn (?YoutubeFeedVideoEntryDto $dto): bool => $dto !== null)
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function extractVideoIds(string $xml): Collection
    {
        return $this->parseEntries($xml)
            ->map(fn (YoutubeFeedVideoEntryDto $entry): string => $entry->videoId)
            ->unique()
            ->values();
    }

    private function loadRoot(string $xml): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $root instanceof SimpleXMLElement ? $root : null;
    }

    private function mapEntry(SimpleXMLElement $entry): ?YoutubeFeedVideoEntryDto
    {
        $entry->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $entry->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');

        $videoIdNodes = $entry->xpath('yt:videoId') ?: [];
        $videoId = isset($videoIdNodes[0]) ? trim((string) $videoIdNodes[0]) : '';
        if ($videoId === '') {
            return null;
        }

        $published = $this->parseTime(isset($entry->published) ? (string) $entry->published : null);
        $updated = $this->parseTime(isset($entry->updated) ? (string) $entry->updated : null);
        $title = isset($entry->title) ? trim((string) $entry->title) : null;

        $url = null;
        $linkNodes = $entry->xpath('atom:link[@rel="alternate"]') ?: [];
        if (isset($linkNodes[0]['href'])) {
            $url = trim((string) $linkNodes[0]['href']);
        }

        return new YoutubeFeedVideoEntryDto(
            videoId: $videoId,
            publishedAt: $published,
            updatedAt: $updated,
            title: $title !== '' ? $title : null,
            url: $url !== '' ? $url : null,
        );
    }

    private function parseTime(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
