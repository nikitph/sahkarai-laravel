<?php

namespace App\Http\Controllers\Archive;

use App\Actions\Archive\DeleteUploadedDocument;
use App\Actions\Archive\StoreUploadedDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\StoreUploadedDocumentRequest;
use App\Models\RegulatoryDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class UploadedDocumentController extends Controller
{
    public function store(StoreUploadedDocumentRequest $request, StoreUploadedDocument $store): RedirectResponse
    {
        $validated = $request->validated();
        $file = $request->file('document');
        abort_unless($file instanceof UploadedFile, 422);
        $document = $store->handle($request->user(), $file, [
            'title' => $request->string('title')->toString(),
            'published_at' => $validated['published_at'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return to_route('archive.show', $document)
            ->with('success', 'PDF uploaded. Extraction and interpretation have started.');
    }

    public function destroy(RegulatoryDocument $document, DeleteUploadedDocument $delete): RedirectResponse
    {
        $this->authorize('delete', $document);
        $delete->handle($document);

        return to_route('archive.index')->with('success', 'Uploaded document deleted.');
    }
}
