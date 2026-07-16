<?php

namespace App\Jobs\Interpretations;

use App\Actions\Interpretations\GenerateLocaleInterpretation;
use App\Actions\Notifications\NotifyRegulatoryUpdate;
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

    public function handle(GenerateLocaleInterpretation $generate, NotifyRegulatoryUpdate $notify): void
    {
        $version = DocumentVersion::findOrFail($this->documentVersionId);
        $interpretation = Interpretation::query()->firstOrCreate(
            ['document_version_id' => $version->getKey()],
            ['status' => 'generating', 'locale_payloads' => [], 'failed_locales' => [], 'locale_attempts' => []],
        );
        if (in_array($interpretation->status, ['published', 'partial', 'failed'], true)) {
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
        $status = $this->status(count($payloads), $exhausted);
        $metadata = $payloads['en'] ?? collect($payloads)->first(fn (array $payload) => array_key_exists('applicability_tags', $payload)) ?? [];
        $applicabilityTags = $metadata['applicability_tags'] ?? $interpretation->applicability_tags ?? [];
        $effectiveDate = $metadata['effective_date'] ?? $interpretation->effective_date?->toDateString();
        $documentType = $metadata['document_type'] ?? $interpretation->document_type;
        $deadlines = $metadata['deadlines'] ?? $interpretation->deadlines ?? [];
        $localePayloads = collect($payloads)->map(
            fn (array $payload) => Arr::except($payload, ['applicability_tags', 'effective_date', 'deadlines', 'document_type']),
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
            'published_at' => in_array($status, ['published', 'partial'], true) ? now() : null,
        ]);

        $version->update([
            'status' => in_array($status, ['published', 'partial'], true) ? 'published' : "interpretation_{$status}",
            'interpretation_status' => $status,
        ]);

        if (in_array($status, ['published', 'partial'], true) && $interpretation->wasChanged('published_at')) {
            $notify->handle($version->fresh(['document']));
        }

        if (! $exhausted && $status === 'generating') {
            throw new RuntimeException('One or more locales require another generation attempt.');
        }
    }

    private function status(int $payloadCount, bool $exhausted): string
    {
        return match (true) {
            $payloadCount === count(SupportedLocale::cases()) => 'published',
            $payloadCount > 0 && $exhausted => 'partial',
            $payloadCount === 0 && $exhausted => 'failed',
            default => 'generating',
        };
    }
}
