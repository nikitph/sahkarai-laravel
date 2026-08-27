<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\SourceAdapter;
use App\Data\DocumentCandidate;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;

class NabardCircularAdapter implements SourceAdapter
{
    public function source(): RegulatorySource
    {
        return RegulatorySource::Nabard;
    }

    public function discover(): iterable
    {
        $url = (string) config('sahkarai.ingestion.sources.nabard.circulars_url');
        $cookies = new CookieJar;
        $request = Http::withOptions(['cookies' => $cookies])
            ->withUserAgent((string) config('sahkarai.ingestion.browser_user_agent'))
            ->timeout(30)->retry(2, 500);
        $initial = $request->get($url)->throw()->body();
        preg_match_all("/__doPostBack\\('([^']+)',''\\)[^>]*>(20\\d{2})<\\/a>/i", html_entity_decode($initial), $matches, PREG_SET_ORDER);
        $targets = collect($matches)->mapWithKeys(fn (array $match) => [(int) $match[2] => html_entity_decode($match[1])]);
        $years = max(1, (int) config('sahkarai.ingestion.sources.nabard.years'));

        for ($year = now()->year; $year > now()->year - $years; $year--) {
            $html = $initial;
            if ($year !== now()->year && $targets->has($year)) {
                $html = $request->asForm()->post($url, [
                    ...AspNetPage::hiddenFields($initial),
                    '__EVENTTARGET' => $targets->get($year),
                    '__EVENTARGUMENT' => '',
                ])->throw()->body();
            } elseif ($year !== now()->year) {
                continue;
            }

            preg_match_all('/<div class="circulars_row">(.*?)(?=<div class="circulars_row">|<\/form>)/is', $html, $rows);
            foreach ($rows[1] as $row) {
                preg_match('/<div class="circulars_date">.*?<span[^>]*>(.*?)<\/span>/is', $row, $dateMatch);
                $date = AspNetPage::text($dateMatch[1] ?? '');
                preg_match_all('/<a\b[^>]*href=["\']([^"\']+\.pdf)["\'][^>]*>(.*?)<\/a>/is', $row, $links, PREG_SET_ORDER);
                $seen = [];
                foreach ($links as $link) {
                    $pdfUrl = $this->absoluteUrl($url, html_entity_decode($link[1]));
                    if (isset($seen[$pdfUrl])) {
                        continue;
                    }
                    $seen[$pdfUrl] = true;
                    yield new DocumentCandidate(
                        source: $this->source(),
                        sourceDocumentId: substr(sha1($pdfUrl), 0, 16),
                        title: AspNetPage::text($link[2]) ?: basename(parse_url($pdfUrl, PHP_URL_PATH) ?: $pdfUrl),
                        downloadUrl: $pdfUrl,
                        sourceUrl: $url,
                        documentType: DocumentType::Circular,
                        publishedAt: $date !== '' ? CarbonImmutable::parse($date) : null,
                    );
                }
            }
        }
    }

    private function absoluteUrl(string $base, string $relative): string
    {
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }

        return (string) UriResolver::resolve(new Uri($base), new Uri($relative));
    }
}
