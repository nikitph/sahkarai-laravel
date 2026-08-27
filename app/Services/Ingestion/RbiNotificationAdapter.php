<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\SourceAdapter;
use App\Data\DocumentCandidate;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

class RbiNotificationAdapter implements SourceAdapter
{
    public function source(): RegulatorySource
    {
        return RegulatorySource::Rbi;
    }

    public function discover(): iterable
    {
        $url = (string) config('sahkarai.ingestion.sources.rbi.notifications_url');
        $years = max(1, (int) config('sahkarai.ingestion.sources.rbi.years'));
        $cookies = new CookieJar;
        for ($year = now()->year; $year > now()->year - $years; $year--) {
            $request = Http::withOptions(['cookies' => $cookies])
                ->withUserAgent((string) config('sahkarai.ingestion.browser_user_agent'))
                ->timeout(30)->retry(2, 500);
            $initial = $request->get($url)->throw()->body();
            $html = $request->asForm()->post($url, [
                ...AspNetPage::hiddenFields($initial),
                'hdnYear' => (string) $year,
                'hdnMonth' => '0',
                'UsrFontCntr$btn' => '',
            ])->throw()->body();

            preg_match_all('/<a\b[^>]*href=["\']?(https:\/\/rbidocs\.rbi\.org\.in\/[^"\' >]+\.PDF)["\']?[^>]*>/i', $html, $links, PREG_OFFSET_CAPTURE);
            foreach ($links[1] as [$pdfUrl, $offset]) {
                $prefix = substr($html, max(0, $offset - 1800), min(1800, $offset));
                preg_match_all('/<a\b[^>]*class=["\']link2["\'][^>]*>(.*?)<\/a>/is', $prefix, $titles);
                preg_match_all('/<b>([^<]*\b20\d{2})<\/b>/i', substr($html, 0, $offset), $dates);
                preg_match_all('/href=NotificationUser\.aspx\?Id=(\d+)&amp;?Mode=0/i', $prefix, $ids);
                $sourceId = (string) (end($ids[1]) ?: pathinfo(parse_url(html_entity_decode($pdfUrl), PHP_URL_PATH) ?: '', PATHINFO_FILENAME));
                $title = AspNetPage::text((string) (end($titles[1]) ?: $sourceId));
                $date = AspNetPage::text((string) (end($dates[1]) ?: ''));

                yield new DocumentCandidate(
                    source: $this->source(),
                    sourceDocumentId: $sourceId,
                    title: $title,
                    downloadUrl: html_entity_decode($pdfUrl, ENT_QUOTES | ENT_HTML5),
                    sourceUrl: "{$url}?Id={$sourceId}&Mode=0",
                    documentType: DocumentType::Circular,
                    publishedAt: $date !== '' ? CarbonImmutable::parse($date) : null,
                );
            }
        }
    }
}
