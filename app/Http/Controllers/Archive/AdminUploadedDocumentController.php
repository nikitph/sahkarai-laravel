<?php

namespace App\Http\Controllers\Archive;

use App\Actions\Archive\StoreAdminUploadedDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\StoreAdminUploadedDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class AdminUploadedDocumentController extends Controller
{
    public function store(
        StoreAdminUploadedDocumentRequest $request,
        StoreAdminUploadedDocument $store,
    ): RedirectResponse {
        $file = $request->file('document');
        abort_unless($file instanceof UploadedFile, 422);
        $document = $store->handle(
            $request->user(),
            $file,
            Arr::except($request->validated(), ['document']),
        );

        return to_route('archive.show', $document)
            ->with('success', 'Shared PDF uploaded. It will publish after extraction succeeds.');
    }
}
