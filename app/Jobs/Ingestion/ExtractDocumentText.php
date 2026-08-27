<?php

namespace App\Jobs\Ingestion;

use App\Actions\Ingestion\CompleteTextExtraction;
use App\Models\DocumentVersion;
use App\Support\Documents\ExtractedTextNormalizer;
use App\Support\Documents\ReadablePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ExtractDocumentText implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $documentVersionId) {}

    public function handle(
        ?CompleteTextExtraction $complete = null,
        ?ReadablePdf $readablePdf = null,
        ?ExtractedTextNormalizer $normalizer = null,
    ): void {
        $complete ??= app(CompleteTextExtraction::class);
        $readablePdf ??= app(ReadablePdf::class);
        $normalizer ??= app(ExtractedTextNormalizer::class);
        $version = DocumentVersion::findOrFail($this->documentVersionId);
        if ($version->extracted_at !== null && filled($version->extracted_text)) {
            $path = $version->extracted_path ?: $complete->artifactPath($version->original_path);
            $disk = Storage::disk(config('sahkarai.ingestion.storage_disk'));
            if (! $disk->exists($path)) {
                if (! $disk->put($path, $version->extracted_text)) {
                    throw new RuntimeException("Unable to persist the extracted artifact at {$path}.");
                }
            }
            if ($version->extracted_path !== $path) {
                $version->update(['extracted_path' => $path]);
            }

            return;
        }

        $attemptNumber = (int) $version->extractionAttempts()->where('method', 'native')->max('attempt') + 1;
        $attempt = $version->extractionAttempts()->create([
            'method' => 'native',
            'provider' => 'smalot/pdfparser',
            'model' => null,
            'status' => 'processing',
            'attempt' => $attemptNumber,
            'started_at' => now(),
        ]);
        $version->update(['status' => 'native_processing', 'extraction_status' => 'native_processing']);

        try {
            $contents = Storage::disk(config('sahkarai.ingestion.storage_disk'))->get($version->original_path);
            $text = match ($version->mime_type) {
                'application/pdf' => $readablePdf->extractText($contents),
                'text/html' => html_entity_decode(strip_tags($contents), ENT_QUOTES | ENT_HTML5),
                'text/plain' => $contents,
                default => throw new RuntimeException("Unsupported document type: {$version->mime_type}"),
            };
            $complete->handle($version, $normalizer->normalize($text), 'native');
            $attempt->update([
                'status' => 'ok',
                'metadata' => ['extracted_characters' => mb_strlen($text)],
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $attempt->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            if ($this->shouldUseKimi($version)) {
                $version->update([
                    'status' => 'kimi_pending',
                    'extraction_status' => 'kimi_pending',
                    'extraction_error' => $exception->getMessage(),
                ]);
                ExtractDocumentTextWithKimi::dispatch($version->getKey())->afterCommit();

                return;
            }

            $version->update([
                'status' => 'needs_review',
                'extraction_status' => 'needs_review',
                'extraction_error' => $exception->getMessage(),
                'needs_review_at' => now(),
            ]);
        }
    }

    private function shouldUseKimi(DocumentVersion $version): bool
    {
        return $version->mime_type === 'application/pdf'
            && $version->document()->whereNull('uploaded_by_user_id')->exists()
            && (bool) config('sahkarai.ingestion.kimi.enabled')
            && filled(config('sahkarai.ingestion.kimi.api_key'));
    }
}
