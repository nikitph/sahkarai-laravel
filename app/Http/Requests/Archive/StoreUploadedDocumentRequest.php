<?php

namespace App\Http\Requests\Archive;

use App\Models\RegulatoryDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreUploadedDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('upload', RegulatoryDocument::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document' => ['required', File::types(['pdf'])->max('5mb')],
        ];
    }
}
