<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_version_id
 * @property string $method
 * @property string|null $provider
 * @property string|null $model
 * @property string $status
 * @property int $attempt
 * @property string|null $request_id
 * @property string|null $error
 * @property array<string, mixed>|null $metadata
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
class ExtractionAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
