<?php

namespace App\Actions\Archive;

use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Models\RegulatoryDocument;
use App\Models\User;
use App\Support\Documents\ReadablePdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreUploadedDocument
{
    public function __construct(private readonly ReadablePdf $readablePdf) {}

    /** @param array{title: string, published_at?: string|null, description?: string|null} $metadata */
    public function handle(User $user, UploadedFile $file, array $metadata): RegulatoryDocument
    {
        $contents = $file->getContent();
        $this->readablePdf->validate($contents);

        $uuid = (string) Str::uuid();
        $date = now()->format('Y/m');
        $path = "originals/user-uploads/{$user->getKey()}/{$date}/{$uuid}.pdf";
        $disk = Storage::disk(config('sahkarai.ingestion.storage_disk'));

        try {
            $document = DB::transaction(function () use ($user, $file, $metadata, $contents, $uuid, $path, $disk): RegulatoryDocument {
                if (! $disk->put($path, $contents)) {
                    throw ValidationException::withMessages(['document' => 'The PDF could not be stored. Please try again.']);
                }

                $document = RegulatoryDocument::query()->create([
                    'source' => RegulatorySource::UserUpload,
                    'source_document_id' => $uuid,
                    'title' => $metadata['title'],
                    'document_type' => DocumentType::Other,
                    'applicability' => Applicability::Generic,
                    'applicability_tags' => [],
                    'published_at' => $metadata['published_at'] ?? null,
                    'source_url' => null,
                    'uploaded_by_user_id' => $user->getKey(),
                    'upload_description' => $metadata['description'] ?? null,
                    'is_backfill' => false,
                ]);
                $version = $document->versions()->create([
                    'version' => 1,
                    'status' => 'acquired',
                    'original_path' => $path,
                    'original_filename' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($contents),
                    'sha256' => hash('sha256', $contents),
                    'acquired_at' => now(),
                ]);

                ExtractDocumentText::dispatch($version->getKey())->afterCommit();

                return $document;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        return $document;
    }
}
