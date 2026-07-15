<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $regulatory_document_id
 * @property int|null $supersedes_id
 * @property int $version
 * @property string $status
 * @property string $original_path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string $sha256
 * @property string|null $extracted_text
 * @property string|null $extracted_path
 * @property string|null $extraction_error
 * @property Carbon $acquired_at
 * @property Carbon|null $extracted_at
 * @property-read RegulatoryDocument $document
 * @property-read DocumentVersion|null $supersedes
 * @property-read Interpretation|null $interpretation
 */
class DocumentVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['acquired_at' => 'datetime', 'extracted_at' => 'datetime'];
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

    public function sourceText(): string
    {
        if ($this->extracted_path) {
            $contents = Storage::disk(config('sahkarai.ingestion.storage_disk'))->get($this->extracted_path);
            if (filled($contents)) {
                return $contents;
            }
        }

        return $this->extracted_text ?? '';
    }
}
