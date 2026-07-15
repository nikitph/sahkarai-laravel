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

            $version->update(['status' => 'extracted', 'extracted_text' => $text, 'extracted_at' => now(), 'extraction_error' => null]);
            GenerateInterpretation::dispatch($version->getKey());
        } catch (Throwable $exception) {
            $version->update(['status' => 'extraction_failed', 'extraction_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function extractPdf(string $contents): string
    {
        return (new Parser)->parseContent($contents)->getText();
    }
}
