<?php

namespace App\Models;

use App\Enums\ExplainerVideoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_version_id
 * @property int|null $requested_by_user_id
 * @property ExplainerVideoStatus $status
 * @property string|null $storage_disk
 * @property string|null $video_path
 * @property string|null $manifest_path
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property int|null $duration_ms
 * @property string|null $input_hash
 * @property string|null $pipeline_version
 * @property int $attempts
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read DocumentVersion $version
 * @property-read User|null $requestedBy
 */
class ExplainerVideo extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ExplainerVideoStatus::class,
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

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
