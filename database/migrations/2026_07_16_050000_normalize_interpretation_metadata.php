<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interpretations', function (Blueprint $table): void {
            $table->jsonb('applicability_tags')->nullable()->after('locale_payloads');
            $table->date('effective_date')->nullable()->after('applicability_tags');
            $table->string('document_type')->nullable()->after('effective_date');
        });

        DB::table('interpretations')->orderBy('id')->eachById(function (object $interpretation): void {
            $payloads = is_string($interpretation->locale_payloads)
                ? json_decode($interpretation->locale_payloads, true)
                : (array) $interpretation->locale_payloads;
            $english = $payloads['en'] ?? [];
            $document = DB::table('document_versions')
                ->join('regulatory_documents', 'regulatory_documents.id', '=', 'document_versions.regulatory_document_id')
                ->where('document_versions.id', $interpretation->document_version_id)
                ->first(['regulatory_documents.applicability_tags', 'regulatory_documents.effective_at', 'regulatory_documents.document_type']);

            $normalizedPayloads = [];
            foreach ($payloads as $locale => $payload) {
                $normalizedPayloads[$locale] = is_array($payload)
                    ? Arr::except($payload, ['applicability_tags', 'effective_date', 'document_type', 'deadlines'])
                    : $payload;
            }
            $existingDeadlines = is_string($interpretation->deadlines)
                ? json_decode($interpretation->deadlines, true)
                : (array) ($interpretation->deadlines ?? []);

            DB::table('interpretations')->where('id', $interpretation->id)->update([
                'locale_payloads' => json_encode($normalizedPayloads, JSON_THROW_ON_ERROR),
                'applicability_tags' => json_encode($english['applicability_tags'] ?? json_decode((string) ($document->applicability_tags ?? '[]'), true) ?? []),
                'effective_date' => $english['effective_date'] ?? $document->effective_at ?? null,
                'document_type' => $english['document_type'] ?? $document->document_type ?? null,
                'deadlines' => json_encode($english['deadlines'] ?? $existingDeadlines, JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('interpretations')->orderBy('id')->eachById(function (object $interpretation): void {
            $payloads = is_string($interpretation->locale_payloads)
                ? json_decode($interpretation->locale_payloads, true)
                : (array) $interpretation->locale_payloads;
            $english = $payloads['en'] ?? [];
            $tags = is_string($interpretation->applicability_tags)
                ? json_decode($interpretation->applicability_tags, true)
                : (array) ($interpretation->applicability_tags ?? []);
            $deadlines = is_string($interpretation->deadlines)
                ? json_decode($interpretation->deadlines, true)
                : (array) ($interpretation->deadlines ?? []);
            $payloads['en'] = [
                ...$english,
                'applicability_tags' => $tags,
                'effective_date' => $interpretation->effective_date,
                'document_type' => $interpretation->document_type,
                'deadlines' => $deadlines,
            ];
            DB::table('interpretations')->where('id', $interpretation->id)->update([
                'locale_payloads' => json_encode($payloads, JSON_THROW_ON_ERROR),
            ]);
        });

        Schema::table('interpretations', function (Blueprint $table): void {
            $table->dropColumn(['applicability_tags', 'effective_date', 'document_type']);
        });
    }
};
