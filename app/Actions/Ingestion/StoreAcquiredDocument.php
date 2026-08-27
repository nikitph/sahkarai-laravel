<?php

namespace App\Actions\Ingestion;

use App\Data\DocumentCandidate;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Models\DocumentVersion;
use App\Models\RegulatoryDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreAcquiredDocument
{
    public function handle(
        DocumentCandidate $candidate,
        string $contents,
        string $mime,
        ?string $originalFilename = null,
        ?string $expectedSha256 = null,
        ?int $expectedBytes = null,
        bool $dispatchExtraction = true,
    ): ?DocumentVersion {
        if ($candidate->documentType === null) {
            throw new RuntimeException('Cannot acquire a candidate without a document type.');
        }
        if ($contents === '') {
            throw new RuntimeException('The acquired regulatory document was empty.');
        }
        if (strlen($contents) > (int) config('sahkarai.ingestion.max_document_bytes')) {
            throw new RuntimeException('The acquired regulatory document exceeds the configured size limit.');
        }
        if ($expectedBytes !== null && strlen($contents) !== $expectedBytes) {
            throw new RuntimeException("The acquired regulatory document size does not match the manifest ({$expectedBytes} expected).");
        }

        $sha256 = hash('sha256', $contents);
        if ($expectedSha256 !== null && ! hash_equals(strtolower($expectedSha256), $sha256)) {
            throw new RuntimeException('The acquired regulatory document checksum does not match the manifest.');
        }

        $mime = Str::before($mime, ';');
        $extension = $this->extension($originalFilename ?: $candidate->downloadUrl, $mime);
        $storedPath = null;

        try {
            $version = DB::transaction(function () use (
                $candidate,
                $contents,
                $sha256,
                $mime,
                $extension,
                $originalFilename,
                &$storedPath,
            ): ?DocumentVersion {
                $document = RegulatoryDocument::query()->firstOrCreate(
                    ['source' => $candidate->source, 'source_document_id' => $candidate->sourceDocumentId],
                    [
                        'title' => $candidate->title,
                        'document_type' => $candidate->documentType,
                        'applicability' => $candidate->applicability,
                        'published_at' => $candidate->publishedAt,
                        'effective_at' => $candidate->effectiveAt,
                        'source_url' => $candidate->sourceUrl,
                        'is_public' => false,
                        'is_backfill' => $candidate->isBackfill,
                    ],
                );

                if (! $document->wasRecentlyCreated) {
                    $this->refreshDiscoveredMetadata($document, $candidate);
                }

                $existing = $document->versions()->where('sha256', $sha256)->first();
                if ($existing) {
                    return null;
                }

                $previous = $document->versions()->lockForUpdate()->latest('version')->first();
                $next = $previous ? $previous->version + 1 : 1;
                $date = ($candidate->publishedAt ?? now())->format('Y/m');
                $sourceId = preg_replace('/[^A-Za-z0-9._-]/', '_', $candidate->sourceDocumentId) ?: "document-{$document->getKey()}";
                $revisionSuffix = $next > 1 ? "-v{$next}" : '';
                $storedPath = "originals/{$candidate->source->storageDirectory()}/{$date}/{$sourceId}{$revisionSuffix}.{$extension}";
                if (! Storage::disk(config('sahkarai.ingestion.storage_disk'))->put($storedPath, $contents)) {
                    throw new RuntimeException("Unable to persist the original document at {$storedPath}.");
                }

                $filename = $originalFilename ?: basename(parse_url($candidate->downloadUrl, PHP_URL_PATH) ?: $storedPath);

                return $document->versions()->create([
                    'supersedes_id' => $previous?->getKey(),
                    'version' => $next,
                    'original_path' => $storedPath,
                    'original_filename' => Str::limit(basename($filename), 255, ''),
                    'mime_type' => $mime,
                    'size_bytes' => strlen($contents),
                    'sha256' => $sha256,
                    'acquired_at' => now(),
                ]);
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk(config('sahkarai.ingestion.storage_disk'))->delete($storedPath);
            }
            throw $exception;
        }

        if ($version && $dispatchExtraction) {
            ExtractDocumentText::dispatch($version->getKey())->afterCommit();
        }

        return $version;
    }

    private function refreshDiscoveredMetadata(RegulatoryDocument $document, DocumentCandidate $candidate): void
    {
        $values = [
            'title' => $candidate->title,
            'document_type' => $candidate->documentType,
            'applicability' => $candidate->applicability,
            'published_at' => $candidate->publishedAt?->toMutable(),
            'effective_at' => $candidate->effectiveAt?->toMutable(),
            'source_url' => $candidate->sourceUrl,
        ];
        foreach ($values as $field => $value) {
            if (! $document->hasManualMetadata($field) && $value !== null) {
                $document->setAttribute($field, $value);
            }
        }
        $document->is_backfill = $document->is_backfill || $candidate->isBackfill;
        $document->save();
    }

    private function extension(string $filenameOrUrl, string $mime): string
    {
        $fromPath = strtolower(pathinfo(parse_url($filenameOrUrl, PHP_URL_PATH) ?: $filenameOrUrl, PATHINFO_EXTENSION));
        if (in_array($fromPath, ['pdf', 'html', 'htm', 'txt'], true)) {
            return $fromPath;
        }

        return match ($mime) {
            'application/pdf' => 'pdf',
            'text/html' => 'html',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
