<?php

namespace App\Jobs\Interpretations;

use App\Actions\Interpretations\GenerateLocaleInterpretation;
use App\Actions\Notifications\NotifyRegulatoryUpdate;
use App\Actions\Videos\QueueExplainerVideo;
use App\Enums\SupportedLocale;
use App\Models\DocumentVersion;
use App\Models\Interpretation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

class GenerateInterpretation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $documentVersionId) {}

    public function handle(GenerateLocaleInterpretation $generate, NotifyRegulatoryUpdate $notify, ?QueueExplainerVideo $queueVideo = null): void
    {
        $queueVideo ??= app(QueueExplainerVideo::class);
        $version = DocumentVersion::findOrFail($this->documentVersionId);
        $interpretation = Interpretation::query()->firstOrCreate(
            ['document_version_id' => $version->getKey()],
            ['status' => 'generating', 'locale_payloads' => [], 'failed_locales' => [], 'locale_attempts' => []],
        );
        if (in_array($interpretation->status, ['published', 'failed'], true)) {
            return;
        }

        $payloads = $interpretation->locale_payloads ?? [];
        $failures = $interpretation->failed_locales ?? [];
        $attempts = $interpretation->locale_attempts ?? [];

        foreach (SupportedLocale::cases() as $locale) {
            if (isset($payloads[$locale->value]) || ($attempts[$locale->value] ?? 0) >= 3) {
                continue;
            }

            $attempts[$locale->value] = ($attempts[$locale->value] ?? 0) + 1;
            try {
                $payloads[$locale->value] = $generate->handle($version, $locale);
                unset($failures[$locale->value]);
            } catch (Throwable $exception) {
                report($exception);
                $failures[$locale->value] = $exception->getMessage();
            }
        }

        $exhausted = collect(SupportedLocale::cases())->every(
            fn (SupportedLocale $locale) => isset($payloads[$locale->value]) || ($attempts[$locale->value] ?? 0) >= 3,
        );
        $status = $this->status(count($payloads), isset($payloads['en']), $exhausted);
        $englishPublished = isset($payloads['en']);
        $metadata = $payloads['en'] ?? collect($payloads)->first(fn (array $payload) => array_key_exists('applicability_tags', $payload)) ?? [];
        $document = $version->document;
        $applicabilityTags = match (true) {
            $document->hasManualMetadata('applicability_tags') => $document->applicability_tags,
            $document->hasManualMetadata('applicability') => [$document->applicability->value],
            default => $metadata['applicability_tags'] ?? $interpretation->applicability_tags ?? [],
        };
        $effectiveDate = $document->hasManualMetadata('effective_at')
            ? $document->effective_at?->toDateString()
            : ($metadata['effective_date'] ?? $interpretation->effective_date?->toDateString());
        $documentType = $document->hasManualMetadata('document_type')
            ? $document->document_type->value
            : ($metadata['document_type'] ?? $interpretation->document_type);
        $deadlines = $metadata['deadlines'] ?? $interpretation->deadlines ?? [];
        $localePayloads = collect($payloads)->map(
            fn (array $payload) => Arr::except($payload, [
                'applicability_tags',
                'effective_date',
                'deadlines',
                'document_type',
                'document_title',
                'regulatory_source',
                'reference_number',
                'published_date',
            ]),
        )->all();

        $interpretation->update([
            'status' => $status,
            'locale_payloads' => $localePayloads,
            'applicability_tags' => $applicabilityTags,
            'effective_date' => $effectiveDate,
            'document_type' => $documentType,
            'failed_locales' => $failures,
            'locale_attempts' => $attempts,
            'deadlines' => $deadlines,
            'model_id' => config('sahkarai.ai.interpretation_model'),
            'prompt_version' => config('sahkarai.ai.prompt_version'),
            'attempts' => max($attempts ?: [0]),
            'terminal_error' => $status === 'failed' ? 'All locale generation attempts failed.' : null,
            'generated_at' => count($payloads) > 0 ? now() : null,
            'published_at' => $englishPublished ? ($interpretation->published_at ?? now()) : null,
        ]);

        $version->update([
            'status' => $englishPublished ? 'published' : "interpretation_{$status}",
            'interpretation_status' => $status,
        ]);

        if ($englishPublished && $interpretation->wasChanged('published_at')) {
            if (! $document->isUserUpload()) {
                $document->update(['is_public' => true]);
            }
            $notify->handle($version->fresh(['document']));
            if (! $version->document->isUserUpload()) {
                $queueVideo->forArchive($version->fresh(['document', 'interpretation']));
            }
        }

        if (! $exhausted && in_array($status, ['generating', 'partial'], true)) {
            throw new RuntimeException('One or more locales require another generation attempt.');
        }
    }

    private function status(int $payloadCount, bool $hasEnglish, bool $exhausted): string
    {
        return match (true) {
            $payloadCount === count(SupportedLocale::cases()) => 'published',
            $hasEnglish => 'partial',
            $exhausted => 'failed',
            default => 'generating',
        };
    }
}
