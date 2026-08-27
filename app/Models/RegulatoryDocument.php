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
 * @property string|null $reference_number
 * @property string $title
 * @property DocumentType $document_type
 * @property Applicability $applicability
 * @property array<int, string> $applicability_tags
 * @property Carbon|null $published_at
 * @property Carbon|null $effective_at
 * @property string|null $source_url
 * @property int|null $uploaded_by_user_id
 * @property int|null $ingested_by_user_id
 * @property string|null $upload_description
 * @property array<int, string> $manual_metadata_fields
 * @property bool $is_public
 * @property bool $is_backfill
 * @property Carbon $created_at
 * @property-read Collection<int, DocumentVersion> $versions
 * @property-read DocumentVersion|null $latestVersion
 * @property-read DocumentVersion|null $latestPublishedVersion
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
            'manual_metadata_fields' => 'array',
            'published_at' => 'date',
            'effective_at' => 'date',
            'is_public' => 'boolean',
            'is_backfill' => 'boolean',
        ];
    }

    /** @param Builder<RegulatoryDocument> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            $query->whereNull('uploaded_by_user_id')
                ->orWhere('uploaded_by_user_id', $user->getKey());

            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where(function (Builder $platform): void {
                $platform->whereNull('uploaded_by_user_id')
                    ->where('is_public', true)
                    ->whereHas('publishedVersions');
            })->orWhere('uploaded_by_user_id', $user->getKey());
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

    /** @return HasMany<DocumentVersion, $this> */
    public function publishedVersions(): HasMany
    {
        return $this->versions()
            ->where('extraction_status', 'ok')
            ->whereIn('interpretation_status', ['published', 'partial'])
            ->whereHas('interpretation', fn (Builder $query) => $query
                ->whereNotNull('published_at')
                ->whereJsonContainsKey('locale_payloads->en'));
    }

    /** @return HasOne<DocumentVersion, $this> */
    public function latestPublishedVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)
            ->ofMany(
                ['version' => 'max'],
                fn (Builder $query) => $query
                    ->where('extraction_status', 'ok')
                    ->whereIn('interpretation_status', ['published', 'partial'])
                    ->whereHas('interpretation', fn (Builder $query) => $query
                        ->whereNotNull('published_at')
                        ->whereJsonContainsKey('locale_payloads->en')),
            );
    }

    public function visibleVersionFor(User $user): ?DocumentVersion
    {
        if ($user->isAdmin() || $this->uploaded_by_user_id === $user->getKey()) {
            if ($this->relationLoaded('latestVersion')) {
                return $this->latestVersion;
            }

            return $this->latestVersion()->with('interpretation')->first();
        }

        if ($this->relationLoaded('latestPublishedVersion')) {
            return $this->latestPublishedVersion;
        }

        return $this->latestPublishedVersion()->with('interpretation')->first();
    }

    public function isVersionVisibleTo(DocumentVersion $version, User $user): bool
    {
        return $version->regulatory_document_id === $this->getKey()
            && ($user->isAdmin() || $this->uploaded_by_user_id === $user->getKey() || $version->isPublished());
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function ingestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingested_by_user_id');
    }

    public function isUserUpload(): bool
    {
        return $this->uploaded_by_user_id !== null;
    }

    public function isAdminUpload(): bool
    {
        return $this->ingested_by_user_id !== null;
    }

    public function hasManualMetadata(string $field): bool
    {
        return in_array($field, $this->manual_metadata_fields ?? [], true);
    }
}
