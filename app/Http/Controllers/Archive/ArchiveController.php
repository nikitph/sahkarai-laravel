<?php

namespace App\Http\Controllers\Archive;

use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Enums\SupportedLocale;
use App\Http\Controllers\Controller;
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

        $documents = $archive->search($filters);
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
        ]);
    }

    public function show(Request $request, RegulatoryDocument $document): Response
    {
        $validated = $request->validate([
            'locale' => ['nullable', Rule::enum(SupportedLocale::class)],
        ]);
        $document->load(['versions.interpretation', 'latestVersion.interpretation']);
        DocumentView::query()->updateOrCreate(
            ['user_id' => $request->user()->getKey(), 'regulatory_document_id' => $document->getKey()],
            ['last_viewed_at' => now()],
        );

        $version = $document->latestVersion;
        $canInterpret = $request->user()->canUseInterpretations();
        $requestedLocale = $validated['locale'] ?? $request->user()->locale->value;
        $availableLocale = $canInterpret && $version?->interpretation
            ? (isset($version->interpretation->locale_payloads[$requestedLocale]) ? $requestedLocale : 'en')
            : null;

        return Inertia::render('archive/show', [
            'document' => [
                ...$document->only(['id', 'title', 'source', 'source_document_id', 'document_type', 'applicability', 'published_at', 'effective_at', 'source_url']),
                'latest_version' => $version ? [
                    ...$version->only(['id', 'version', 'status', 'original_filename', 'mime_type', 'size_bytes', 'acquired_at']),
                    'interpretation' => $canInterpret ? $version->interpretation?->payloadFor($requestedLocale) : null,
                    'interpretation_locale' => $availableLocale,
                    'requested_locale' => $requestedLocale,
                    'locale_fallback' => $availableLocale === 'en' && $requestedLocale !== 'en',
                    'interpretation_meta' => $canInterpret ? $version->interpretation?->only(['id', 'status', 'model_id', 'prompt_version', 'generated_at']) : null,
                ] : null,
                'versions' => $document->versions->map->only(['id', 'version', 'status', 'acquired_at']),
            ],
            'capabilities' => [
                'interpretations' => $canInterpret,
                'exports' => $request->user()->tier->canExportDocuments(),
                'chat' => $request->user()->canUseChat(),
            ],
        ]);
    }

    public function download(Request $request, RegulatoryDocument $document): StreamedResponse
    {
        $version = $document->latestVersion()->firstOrFail();

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

        return [
            ...$document->only(['id', 'title', 'source', 'document_type', 'applicability', 'published_at', 'effective_at']),
            'version' => $document->latestVersion?->version,
            'status' => $document->latestVersion?->status,
            'snippet' => $english ? str($english['summary'])->limit(180)->toString() : str($document->latestVersion?->extracted_text)->limit(180)->toString(),
            'matched_field' => $matchedField,
        ];
    }
}
