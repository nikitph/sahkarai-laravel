<?php

namespace App\Actions\Ingestion;

use App\Data\DocumentCandidate;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Models\DocumentVersion;
use App\Models\RegulatoryDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AcquireDocument
{
    public function handle(DocumentCandidate $candidate): ?DocumentVersion
    {
        if ($candidate->documentType === null) {
            throw new RuntimeException('Cannot acquire a candidate without a document type.');
        }

        $response = Http::timeout(60)->retry(2, 500)->get($candidate->downloadUrl)->throw();
        $contents = $response->body();
        if ($contents === '') {
            throw new RuntimeException('The acquired regulatory document was empty.');
        }
        if (strlen($contents) > (int) config('sahkarai.ingestion.max_document_bytes')) {
            throw new RuntimeException('The acquired regulatory document exceeds the configured size limit.');
        }

        $sha256 = hash('sha256', $contents);
        $mime = $response->header('Content-Type') ?: 'application/octet-stream';
        $extension = $this->extension($candidate->downloadUrl, $mime);

        $version = DB::transaction(function () use ($candidate, $contents, $sha256, $mime, $extension): ?DocumentVersion {
            $document = RegulatoryDocument::query()->firstOrCreate(
                ['source' => $candidate->source, 'source_document_id' => $candidate->sourceDocumentId],
                [
                    'title' => $candidate->title,
                    'document_type' => $candidate->documentType,
                    'applicability' => $candidate->applicability,
                    'published_at' => $candidate->publishedAt,
                    'effective_at' => $candidate->effectiveAt,
                    'source_url' => $candidate->sourceUrl,
                    'is_backfill' => $candidate->isBackfill,
                ],
            );

            $existing = $document->versions()->where('sha256', $sha256)->first();
            if ($existing) {
                return null;
            }

            $previous = $document->versions()->lockForUpdate()->latest('version')->first();
            $next = $previous ? $previous->version + 1 : 1;
            $date = ($candidate->publishedAt ?? now())->format('Y/m');
            $sourceId = preg_replace('/[^A-Za-z0-9._-]/', '_', $candidate->sourceDocumentId) ?: "document-{$document->getKey()}";
            $revisionSuffix = $next > 1 ? "-v{$next}" : '';
            $path = "originals/{$candidate->source->storageDirectory()}/{$date}/{$sourceId}{$revisionSuffix}.{$extension}";
            if (! Storage::disk(config('sahkarai.ingestion.storage_disk'))->put($path, $contents)) {
                throw new RuntimeException("Unable to persist the original document at {$path}.");
            }

            return $document->versions()->create([
                'supersedes_id' => $previous?->getKey(),
                'version' => $next,
                'original_path' => $path,
                'original_filename' => basename(parse_url($candidate->downloadUrl, PHP_URL_PATH) ?: $path),
                'mime_type' => Str::before($mime, ';'),
                'size_bytes' => strlen($contents),
                'sha256' => $sha256,
                'acquired_at' => now(),
            ]);
        }, attempts: 3);

        if ($version) {
            ExtractDocumentText::dispatch($version->getKey());
        }

        return $version;
    }

    private function extension(string $url, string $mime): string
    {
        $fromUrl = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (in_array($fromUrl, ['pdf', 'html', 'htm', 'txt'], true)) {
            return $fromUrl;
        }

        return match (Str::before($mime, ';')) {
            'application/pdf' => 'pdf',
            'text/html' => 'html',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
