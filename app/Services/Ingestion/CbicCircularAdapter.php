<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\SourceAdapter;
use App\Data\DocumentCandidate;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class CbicCircularAdapter implements SourceAdapter
{
    public function source(): RegulatorySource
    {
        return RegulatorySource::Cbic;
    }

    public function discover(): iterable
    {
        $base = rtrim((string) config('sahkarai.ingestion.sources.cbic.base_url'), '/');
        $years = max(1, (int) config('sahkarai.ingestion.sources.cbic.years'));
        for ($year = now()->year; $year > now()->year - $years; $year--) {
            $catalogueUrl = "{$base}/api/cbic-circular-msts/fetchCircularByYear/{$year}";
            $rows = Http::withUserAgent((string) config('sahkarai.ingestion.browser_user_agent'))
                ->acceptJson()->timeout(30)->retry(2, 500)->get($catalogueUrl)->throw()->json();
            foreach (is_array($rows) ? $rows : [] as $row) {
                if (! is_array($row) || blank($row['id'] ?? null)) {
                    continue;
                }
                $path = $this->repairPath((string) ($row['docFilePath'] ?? ''));
                if ($path === '') {
                    continue;
                }

                yield new DocumentCandidate(
                    source: $this->source(),
                    sourceDocumentId: (string) $row['id'],
                    title: trim((string) ($row['circularName'] ?? $row['circularNo'] ?? $row['docFileName'] ?? $row['id'])),
                    downloadUrl: "{$base}/content/pdf/".$this->encodePath($path),
                    sourceUrl: $catalogueUrl,
                    documentType: DocumentType::Circular,
                    publishedAt: filled($row['circularDt'] ?? null) ? CarbonImmutable::parse((string) $row['circularDt']) : null,
                );
            }
        }
    }

    private function repairPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = str_replace('/Circulars/', '/circulars/', $path);
        if (str_starts_with($path, 'tax_repositorycustomscirculars')) {
            $path = preg_replace('/^tax_repositorycustomscirculars/', 'tax_repository/customs/circulars/', $path) ?? $path;
        }

        return str_replace('cs-circulars-2020Circular-29-2020', 'cs-circulars-2020/Circular-29-2020', $path);
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
