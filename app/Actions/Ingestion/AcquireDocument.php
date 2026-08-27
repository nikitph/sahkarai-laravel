<?php

namespace App\Actions\Ingestion;

use App\Data\DocumentCandidate;
use App\Enums\RegulatorySource;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AcquireDocument
{
    public function __construct(private readonly StoreAcquiredDocument $store) {}

    public function handle(DocumentCandidate $candidate): ?DocumentVersion
    {
        $response = Http::withUserAgent((string) config('sahkarai.ingestion.browser_user_agent'))
            ->timeout(60)->retry(2, 500)->get($candidate->downloadUrl)->throw();
        $mime = $response->header('Content-Type') ?: 'application/octet-stream';
        $contents = $response->body();
        if ($candidate->source === RegulatorySource::Cbic) {
            $encoded = $response->json('data');
            $contents = is_string($encoded) ? (base64_decode($encoded, true) ?: '') : '';
            if (! str_starts_with(ltrim($contents), '%PDF-')) {
                throw new RuntimeException('CBIC returned an invalid PDF payload.');
            }
            $mime = 'application/pdf';
        }

        return $this->store->handle(
            candidate: $candidate,
            contents: $contents,
            mime: $mime,
        );
    }
}
