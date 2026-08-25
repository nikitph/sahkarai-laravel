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
        $videos = $document->versions()
            ->with('explainerVideo')
            ->get()
            ->pluck('explainerVideo')
            ->filter()
            ->map(fn ($video): array => ['disk' => $video->storage_disk, 'paths' => array_filter([$video->video_path, $video->manifest_path])]);

        DB::transaction(fn () => $document->delete());
        Storage::disk(config('sahkarai.ingestion.storage_disk'))->delete($paths);
        $videos->each(fn (array $video) => $video['disk'] ? Storage::disk($video['disk'])->delete($video['paths']) : null);
    }
}
