<?php

namespace App\Models;

use App\Support\Documents\ExtractedTextNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $regulatory_document_id
 * @property int|null $supersedes_id
 * @property int $version
 * @property string $status
 * @property string $extraction_status
 * @property string|null $extraction_method
 * @property string $interpretation_status
 * @property string $video_status
 * @property string $original_path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string $sha256
 * @property string|null $extracted_text
 * @property string|null $extracted_path
 * @property string|null $extracted_text_sha256
 * @property string|null $extraction_error
 * @property Carbon $acquired_at
 * @property Carbon|null $extracted_at
 * @property Carbon|null $needs_review_at
 * @property-read RegulatoryDocument $document
 * @property-read DocumentVersion|null $supersedes
 * @property-read Interpretation|null $interpretation
 * @property-read ExplainerVideo|null $explainerVideo
 */
class DocumentVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'datetime',
            'extracted_at' => 'datetime',
            'needs_review_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RegulatoryDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(RegulatoryDocument::class, 'regulatory_document_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return HasOne<Interpretation, $this> */
    public function interpretation(): HasOne
    {
        return $this->hasOne(Interpretation::class);
    }

    /** @return HasMany<ExtractionAttempt, $this> */
    public function extractionAttempts(): HasMany
    {
        return $this->hasMany(ExtractionAttempt::class);
    }

    /** @return HasOne<ExplainerVideo, $this> */
    public function explainerVideo(): HasOne
    {
        return $this->hasOne(ExplainerVideo::class);
    }

    public function sourceText(): string
    {
        if ($this->extracted_path) {
            $contents = Storage::disk(config('sahkarai.ingestion.storage_disk'))->get($this->extracted_path);
            if (filled($contents)) {
                return app(ExtractedTextNormalizer::class)->normalize($contents);
            }
        }

        return app(ExtractedTextNormalizer::class)->normalize($this->extracted_text ?? '');
    }

    public function isReadyForChat(): bool
    {
        if (
            $this->extraction_status !== 'ok'
            || blank($this->extracted_text)
            || ! in_array($this->interpretation_status, ['published', 'partial'], true)
        ) {
            return false;
        }

        $interpretation = $this->relationLoaded('interpretation')
            ? $this->interpretation
            : $this->interpretation()->first();

        return $interpretation !== null
            && in_array($interpretation->status, ['published', 'partial'], true)
            && isset($interpretation->locale_payloads['en'])
            && ! empty($interpretation->locale_payloads);
    }

    public function isPublished(): bool
    {
        if ($this->extraction_status !== 'ok' || ! in_array($this->interpretation_status, ['published', 'partial'], true)) {
            return false;
        }

        $interpretation = $this->relationLoaded('interpretation')
            ? $this->interpretation
            : $this->interpretation()->first();

        return $interpretation !== null
            && $interpretation->published_at !== null
            && isset($interpretation->locale_payloads['en']);
    }
}
