<?php

namespace App\Http\Controllers\Archive;

use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Enums\SupportedLocale;
use App\Http\Controllers\Controller;
use App\Models\DocumentVersion;
use App\Models\DocumentView;
use App\Models\RegulatoryDocument;
use App\Services\Archive\ArchiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveController extends Controller
{
    public function index(Request $request, ArchiveSearch $archive): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', Rule::enum(RegulatorySource::class)],
            'document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'applicability' => ['nullable', Rule::enum(Applicability::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['newest', 'title'])],
        ]);

        $documents = $archive->search($filters, $request->user());
        $serializedDocuments = $documents->toArray();
        $serializedDocuments['data'] = $documents->getCollection()->map(fn (RegulatoryDocument $document) => $this->summary($document, $filters['q'] ?? null))->all();

        return Inertia::render('archive/index', [
            'documents' => $serializedDocuments,
            'filters' => $filters,
            'filterOptions' => [
                'sources' => collect(RegulatorySource::cases())->map(fn (RegulatorySource $source) => ['value' => $source->value]),
                'types' => collect(DocumentType::cases())->map(fn (DocumentType $type) => ['value' => $type->value]),
                'applicability' => collect(Applicability::cases())->map(fn (Applicability $tag) => ['value' => $tag->value]),
            ],
            'capabilities' => [
                'uploads' => $request->user()->canUploadDocuments(),
                'maxUploadBytes' => 5 * 1024 * 1024,
            ],
        ]);
    }

    public function show(Request $request, RegulatoryDocument $document): Response
    {
        $this->authorize('view', $document);
        $validated = $request->validate([
            'locale' => ['nullable', Rule::enum(SupportedLocale::class)],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);
        $document->load(['versions.interpretation', 'latestVersion.interpretation']);
        $version = isset($validated['version'])
            ? $document->versions->firstWhere('id', $validated['version'])
            : $document->latestVersion;
        abort_unless($version instanceof DocumentVersion, 404);
        DocumentView::query()->updateOrCreate(
            ['user_id' => $request->user()->getKey(), 'document_version_id' => $version->getKey()],
            ['regulatory_document_id' => $document->getKey(), 'last_viewed_at' => now()],
        );
        $canInterpret = $request->user()->canUseInterpretations();
        $requestedLocale = $validated['locale'] ?? $request->user()->locale->value;
        $availableLocale = $canInterpret && $version->interpretation
            ? (isset($version->interpretation->locale_payloads[$requestedLocale]) ? $requestedLocale : 'en')
            : null;

        return Inertia::render('archive/show', [
            'document' => [
                ...$document->only(['id', 'title', 'source', 'source_document_id', 'document_type', 'applicability', 'published_at', 'effective_at', 'source_url', 'upload_description']),
                'is_user_upload' => $document->isUserUpload(),
                'latest_version' => [
                    ...$version->only(['id', 'version', 'status', 'extraction_status', 'interpretation_status', 'original_filename', 'mime_type', 'size_bytes', 'acquired_at', 'extraction_error']),
                    'interpretation' => $canInterpret ? $version->interpretation?->payloadFor($requestedLocale) : null,
                    'interpretation_locale' => $availableLocale,
                    'requested_locale' => $requestedLocale,
                    'locale_fallback' => $availableLocale === 'en' && $requestedLocale !== 'en',
                    'interpretation_meta' => $canInterpret ? $version->interpretation?->only(['id', 'status', 'model_id', 'prompt_version', 'generated_at']) : null,
                ],
                'versions' => $document->versions->map->only(['id', 'version', 'status', 'acquired_at']),
            ],
            'capabilities' => [
                'interpretations' => $canInterpret,
                'exports' => $request->user()->tier->canExportDocuments(),
                'chat' => $request->user()->canUseChat(),
                'delete' => $request->user()->can('delete', $document),
            ],
        ]);
    }

    public function download(Request $request, RegulatoryDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);
        $validated = $request->validate(['version' => ['nullable', 'integer', 'min:1']]);
        $version = isset($validated['version'])
            ? $document->versions()->whereKey($validated['version'])->firstOrFail()
            : $document->latestVersion()->firstOrFail();

        return Storage::disk(config('sahkarai.ingestion.storage_disk'))->download(
            $version->original_path,
            $version->original_filename ?: "document-{$document->getKey()}-v{$version->version}",
        );
    }

    /** @return array<string, mixed> */
    private function summary(RegulatoryDocument $document, ?string $query = null): array
    {
        $english = $document->latestVersion?->interpretation?->payloadFor('en');
        $text = $document->latestVersion->extracted_text ?? '';
        $englishText = $english ? json_encode($english, JSON_THROW_ON_ERROR) : '';
        $needle = mb_strtolower(trim($query ?? '', ' "'));
        $matchedField = match (true) {
            $needle === '' => null,
            str_contains(mb_strtolower($document->title), $needle) => 'title',
            str_contains(mb_strtolower($text), $needle) => 'extracted_text',
            str_contains(mb_strtolower($englishText), $needle) => 'english_interpretation',
            default => 'localized_interpretation',
        };
        $snippet = match ($matchedField) {
            'title' => $document->title,
            'extracted_text' => $text,
            default => $english['summary'] ?? $text,
        };

        return [
            ...$document->only(['id', 'title', 'source', 'document_type', 'applicability', 'applicability_tags', 'published_at', 'effective_at']),
            'is_user_upload' => $document->isUserUpload(),
            'version' => $document->latestVersion?->version,
            'status' => $document->latestVersion?->status,
            'extraction_status' => $document->latestVersion?->extraction_status,
            'interpretation_status' => $document->latestVersion?->interpretation_status,
            'snippet' => str($snippet)->limit(180)->toString(),
            'matched_field' => $matchedField,
        ];
    }
}
