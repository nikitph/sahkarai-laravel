<?php

namespace App\Actions\Archive;

use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Models\DocumentVersion;
use App\Models\RegulatoryDocument;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Documents\ReadablePdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreAdminUploadedDocument
{
    public function __construct(
        private readonly ReadablePdf $readablePdf,
        private readonly Audit $audit,
    ) {}

    /**
     * @param array{
     *   title?: string|null,
     *   source?: string|null,
     *   reference_number?: string|null,
     *   document_type?: string|null,
     *   published_at?: string|null,
     *   effective_at?: string|null,
     *   applicability?: string|null,
     *   applicability_tags?: array<int, string>|null,
     *   description?: string|null
     * } $metadata
     */
    public function handle(User $admin, UploadedFile $file, array $metadata): RegulatoryDocument
    {
        $contents = $file->getContent();
        $this->readablePdf->validate($contents);
        $sha256 = hash('sha256', $contents);
        $duplicate = DocumentVersion::query()->where('sha256', $sha256)->with('document')->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'document' => "This PDF already exists in the archive as “{$duplicate->document->title}” (document {$duplicate->document->getKey()}).",
            ]);
        }

        $uuid = (string) Str::uuid();
        $date = now()->format('Y/m');
        $path = "originals/admin-uploads/{$date}/{$uuid}.pdf";
        $disk = Storage::disk(config('sahkarai.ingestion.storage_disk'));
        $filename = Str::limit(basename($file->getClientOriginalName()), 255, '');
        $fallbackTitle = Str::limit(
            str(pathinfo($filename, PATHINFO_FILENAME))->replace(['-', '_'], ' ')->squish()->title()->toString(),
            255,
            '',
        );
        $manualFields = collect($metadata)
            ->filter(fn (mixed $value) => is_array($value) ? $value !== [] : filled($value))
            ->keys()
            ->map(fn (string $field) => $field === 'description' ? 'upload_description' : $field)
            ->values()
            ->all();

        try {
            $document = DB::transaction(function () use (
                $admin,
                $metadata,
                $contents,
                $sha256,
                $uuid,
                $path,
                $disk,
                $filename,
                $fallbackTitle,
                $manualFields,
            ): RegulatoryDocument {
                if (! $disk->put($path, $contents)) {
                    throw ValidationException::withMessages(['document' => 'The PDF could not be stored. Please try again.']);
                }

                $tags = $metadata['applicability_tags'] ?? [];
                $document = RegulatoryDocument::query()->create([
                    'source' => $metadata['source'] ?? RegulatorySource::UserUpload,
                    'source_document_id' => $uuid,
                    'reference_number' => $metadata['reference_number'] ?? null,
                    'title' => $metadata['title'] ?? ($fallbackTitle ?: 'Untitled regulatory document'),
                    'document_type' => $metadata['document_type'] ?? DocumentType::Other,
                    'applicability' => $metadata['applicability'] ?? ($tags[0] ?? Applicability::Generic),
                    'applicability_tags' => $tags,
                    'published_at' => $metadata['published_at'] ?? null,
                    'effective_at' => $metadata['effective_at'] ?? null,
                    'source_url' => null,
                    'uploaded_by_user_id' => null,
                    'ingested_by_user_id' => $admin->getKey(),
                    'upload_description' => $metadata['description'] ?? null,
                    'manual_metadata_fields' => $manualFields,
                    'is_public' => false,
                    'is_backfill' => false,
                ]);
                $version = $document->versions()->create([
                    'version' => 1,
                    'status' => 'acquired',
                    'original_path' => $path,
                    'original_filename' => $filename,
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($contents),
                    'sha256' => $sha256,
                    'acquired_at' => now(),
                ]);

                ExtractDocumentText::dispatch($version->getKey())->afterCommit();

                return $document;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        $this->audit->record('archive.admin_pdf_uploaded', $document, [
            'manual_metadata_fields' => $manualFields,
            'size_bytes' => strlen($contents),
        ]);

        return $document;
    }
}
