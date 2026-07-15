<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\SourceAdapter;
use App\Data\DocumentCandidate;
use App\Enums\RegulatorySource;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class ConfiguredFeedAdapter implements SourceAdapter
{
    public function __construct(private readonly RegulatorySource $regulatorySource) {}

    public function source(): RegulatorySource
    {
        return $this->regulatorySource;
    }

    public function discover(): iterable
    {
        $url = config("sahkarai.ingestion.sources.{$this->source()->value}.feed_url");
        if (! $url) {
            throw new RuntimeException("No feed URL is configured for {$this->source()->value}.");
        }

        $body = Http::timeout(30)->retry(2, 500)->get($url)->throw()->body();
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        if ($xml === false) {
            throw new RuntimeException("Invalid XML feed returned by {$this->source()->value}.");
        }

        $channelItems = $xml->channel->item;
        $items = count($channelItems) > 0 ? $channelItems : $xml->entry;
        foreach ($items as $item) {
            $link = (string) ($item->enclosure['url'] ?? $item->link['href'] ?? $item->link ?? '');
            $id = trim((string) ($item->guid ?? $item->id ?? $link));
            $date = trim((string) ($item->pubDate ?? $item->published ?? $item->updated ?? ''));

            yield new DocumentCandidate(
                source: $this->source(),
                sourceDocumentId: $id,
                title: trim((string) ($item->title ?? '')),
                downloadUrl: $link,
                sourceUrl: $link !== '' ? $link : null,
                publishedAt: $this->parseDate($date),
            );
        }
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        if ($date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
