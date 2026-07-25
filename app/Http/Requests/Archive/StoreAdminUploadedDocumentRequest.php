<?php

namespace App\Http\Requests\Archive;

use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Models\RegulatoryDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreAdminUploadedDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('uploadShared', RegulatoryDocument::class);
    }

    protected function prepareForValidation(): void
    {
        $tags = collect(explode(',', (string) $this->input('applicability_tags')))
            ->map(fn (string $tag) => trim(strtolower($tag)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge(['applicability_tags' => $tags]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(collect(RegulatorySource::pollableCases())->pluck('value')->all())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'published_at' => ['nullable', 'date'],
            'effective_at' => ['nullable', 'date'],
            'applicability' => ['nullable', Rule::enum(Applicability::class)],
            'applicability_tags' => ['array', 'max:6'],
            'applicability_tags.*' => ['required', Rule::enum(Applicability::class), 'distinct'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document' => ['required', File::types(['pdf'])->max('5mb')],
        ];
    }
}
