<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_version_id
 * @property string $status
 * @property array<string, array<string, mixed>>|null $locale_payloads
 * @property array<int, string>|null $applicability_tags
 * @property Carbon|null $effective_date
 * @property string|null $document_type
 * @property array<string, string>|null $failed_locales
 * @property array<string, int>|null $locale_attempts
 * @property array<int, array<string, mixed>>|null $deadlines
 * @property string|null $model_id
 * @property string|null $prompt_version
 * @property int $attempts
 * @property Carbon|null $generated_at
 * @property Carbon|null $published_at
 * @property-read DocumentVersion $version
 */
class Interpretation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'locale_payloads' => 'array',
            'applicability_tags' => 'array',
            'effective_date' => 'date',
            'failed_locales' => 'array',
            'locale_attempts' => 'array',
            'deadlines' => 'array',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /** @return HasMany<IssueReport, $this> */
    public function issueReports(): HasMany
    {
        return $this->hasMany(IssueReport::class);
    }

    /** @return array<string, mixed>|null */
    public function payloadFor(string $locale): ?array
    {
        $payloads = $this->locale_payloads ?? [];

        $localized = $payloads[$locale] ?? $payloads['en'] ?? null;
        if ($localized === null) {
            return null;
        }

        return [
            ...$localized,
            'applicability_tags' => $this->applicability_tags ?? [],
            'effective_date' => $this->effective_date?->toDateString(),
            'deadlines' => $this->deadlines ?? [],
            'document_type' => $this->document_type,
        ];
    }
}
