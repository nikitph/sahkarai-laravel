<?php

namespace App\Jobs\Ingestion;

use App\Actions\Ingestion\CompleteTextExtraction;
use App\Models\DocumentVersion;
use App\Services\Ingestion\KimiFileExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExtractDocumentTextWithKimi implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $documentVersionId)
    {
        $this->tries = max(1, (int) config('sahkarai.ingestion.kimi.max_attempts'));
    }

    public function handle(KimiFileExtractor $extractor, CompleteTextExtraction $complete): void
    {
        $version = DocumentVersion::with('document')->findOrFail($this->documentVersionId);
        if ($version->extraction_status === 'ok') {
            return;
        }
        if ($version->document->isUserUpload() || $version->mime_type !== 'application/pdf') {
            $this->markNeedsReview('Kimi extraction is limited to platform-owned PDF versions.');

            return;
        }
        if (! config('sahkarai.ingestion.kimi.enabled') || blank(config('sahkarai.ingestion.kimi.api_key'))) {
            $this->markNeedsReview('Kimi extraction is disabled or not configured.');

            return;
        }

        $attemptNumber = (int) $version->extractionAttempts()->where('method', 'kimi')->max('attempt') + 1;
        $attempt = $version->extractionAttempts()->create([
            'method' => 'kimi',
            'provider' => 'moonshot',
            'model' => 'file-extract',
            'status' => 'processing',
            'attempt' => $attemptNumber,
            'started_at' => now(),
        ]);
        $version->update(['status' => 'kimi_processing', 'extraction_status' => 'kimi_processing']);

        try {
            $contents = Storage::disk(config('sahkarai.ingestion.storage_disk'))->get($version->original_path);
            $result = $extractor->extract($contents, $version->original_filename ?: "document-{$version->getKey()}.pdf");
            $complete->handle($version, $result->text, 'kimi');
            $attempt->update([
                'status' => 'ok',
                'request_id' => $result->requestId,
                'metadata' => [...$result->metadata, 'extracted_characters' => mb_strlen($result->text)],
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $attempt->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
            $version->update([
                'status' => 'kimi_pending',
                'extraction_status' => 'kimi_pending',
                'extraction_error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markNeedsReview($exception?->getMessage() ?: 'Kimi extraction exhausted all attempts.');
    }

    private function markNeedsReview(string $error): void
    {
        DocumentVersion::query()->whereKey($this->documentVersionId)->update([
            'status' => 'needs_review',
            'extraction_status' => 'needs_review',
            'needs_review_at' => now(),
            'extraction_error' => $error,
        ]);
    }
}
