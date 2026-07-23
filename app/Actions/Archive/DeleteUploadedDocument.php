<?php

namespace App\Actions\Archive;

use App\Models\RegulatoryDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUploadedDocument
{
    public function handle(RegulatoryDocument $document): void
    {
        $paths = $document->versions()
            ->get(['original_path', 'extracted_path'])
            ->flatMap(fn ($version) => [$version->original_path, $version->extracted_path])
            ->filter()
            ->values()
            ->all();

        DB::transaction(fn () => $document->delete());
        Storage::disk(config('sahkarai.ingestion.storage_disk'))->delete($paths);
    }
}
