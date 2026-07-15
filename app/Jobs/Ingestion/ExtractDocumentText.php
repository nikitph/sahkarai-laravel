<?php

namespace App\Jobs\Ingestion;

use App\Jobs\Interpretations\GenerateInterpretation;
use App\Models\DocumentVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class ExtractDocumentText implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $documentVersionId) {}

    public function handle(): void
    {
        $version = DocumentVersion::findOrFail($this->documentVersionId);
        if ($version->extracted_at !== null && filled($version->extracted_text)) {
            $path = $version->extracted_path ?: $this->artifactPath($version->original_path);
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

        try {
            $contents = Storage::disk(config('sahkarai.ingestion.storage_disk'))->get($version->original_path);
            $text = match ($version->mime_type) {
                'application/pdf' => $this->extractPdf($contents),
                'text/html' => html_entity_decode(strip_tags($contents), ENT_QUOTES | ENT_HTML5),
                'text/plain' => $contents,
                default => throw new RuntimeException("Unsupported document type: {$version->mime_type}"),
            };
            $text = trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\R{3,}/', "\n\n", $text)) ?? '');
            if ($text === '') {
                throw new RuntimeException('Text extraction produced no content.');
            }

            $extractedPath = $this->artifactPath($version->original_path);
            if (! Storage::disk(config('sahkarai.ingestion.storage_disk'))->put($extractedPath, $text)) {
                throw new RuntimeException("Unable to persist the extracted artifact at {$extractedPath}.");
            }
            $version->update([
                'status' => 'extracted',
                'extraction_status' => 'ok',
                'extracted_text' => $text,
                'extracted_path' => $extractedPath,
                'extracted_at' => now(),
                'extraction_error' => null,
            ]);
            GenerateInterpretation::dispatch($version->getKey());
        } catch (Throwable $exception) {
            $version->update([
                'status' => 'extraction_failed',
                'extraction_status' => 'failed',
                'extraction_error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    private function extractPdf(string $contents): string
    {
        return (new Parser)->parseContent($contents)->getText();
    }

    private function artifactPath(string $originalPath): string
    {
        $relative = str_starts_with($originalPath, 'originals/')
            ? substr($originalPath, strlen('originals/'))
            : basename($originalPath);

        return 'extracted/'.preg_replace('/\.[^.\/]+$/', '', $relative).'.txt';
    }
}
