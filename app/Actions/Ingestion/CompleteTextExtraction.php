<?php

namespace App\Actions\Ingestion;

use App\Jobs\Interpretations\GenerateInterpretation;
use App\Models\DocumentVersion;
use App\Support\Documents\ExtractedTextNormalizer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CompleteTextExtraction
{
    public function __construct(private readonly ExtractedTextNormalizer $normalizer) {}

    public function handle(DocumentVersion $version, string $text, string $method): void
    {
        $text = $this->normalizer->normalize($text);
        $minimum = max(1, (int) config('sahkarai.ingestion.minimum_extracted_characters'));
        if (mb_strlen($text) < $minimum) {
            throw new RuntimeException("Text extraction produced fewer than {$minimum} useful characters.");
        }

        $path = $this->artifactPath($version->original_path);
        if (! Storage::disk(config('sahkarai.ingestion.storage_disk'))->put($path, $text)) {
            throw new RuntimeException("Unable to persist the extracted artifact at {$path}.");
        }

        $version->update([
            'status' => 'extracted',
            'extraction_status' => 'ok',
            'extraction_method' => $method,
            'extracted_text' => $text,
            'extracted_path' => $path,
            'extracted_text_sha256' => hash('sha256', $text),
            'extracted_at' => now(),
            'needs_review_at' => null,
            'extraction_error' => null,
        ]);

        GenerateInterpretation::dispatch($version->getKey())->afterCommit();
    }

    public function artifactPath(string $originalPath): string
    {
        $relative = str_starts_with($originalPath, 'originals/')
            ? substr($originalPath, strlen('originals/'))
            : basename($originalPath);

        return 'extracted/'.preg_replace('/\.[^.\/]+$/', '', $relative).'.txt';
    }
}
