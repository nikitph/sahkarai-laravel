<?php

namespace App\Models;

use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property RegulatorySource $source
 * @property string $source_document_id
 * @property string $title
 * @property DocumentType $document_type
 * @property Applicability $applicability
 * @property array<int, string> $applicability_tags
 * @property Carbon|null $published_at
 * @property Carbon|null $effective_at
 * @property string|null $source_url
 * @property int|null $uploaded_by_user_id
 * @property string|null $upload_description
 * @property bool $is_backfill
 * @property Carbon $created_at
 * @property-read Collection<int, DocumentVersion> $versions
 * @property-read DocumentVersion|null $latestVersion
 */
class RegulatoryDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source' => RegulatorySource::class,
            'document_type' => DocumentType::class,
            'applicability' => Applicability::class,
            'applicability_tags' => 'array',
            'published_at' => 'date',
            'effective_at' => 'date',
            'is_backfill' => 'boolean',
        ];
    }

    /** @param Builder<RegulatoryDocument> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->whereNull('uploaded_by_user_id')
                ->orWhere('uploaded_by_user_id', $user->getKey());
        });
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /** @return HasOne<DocumentVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('version', 'max');
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function isUserUpload(): bool
    {
        return $this->uploaded_by_user_id !== null;
    }
}
