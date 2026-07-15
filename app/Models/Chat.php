<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $regulatory_document_id
 * @property int $document_version_id
 * @property string|null $title
 * @property string $locale
 * @property string $status
 * @property int $context_tokens
 * @property Carbon|null $context_closed_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read RegulatoryDocument $document
 * @property-read DocumentVersion $version
 * @property-read Collection<int, ChatMessage> $messages
 * @property-read ChatMessage|null $latestMessage
 */
class Chat extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'locale' => 'en',
        'status' => 'active',
        'context_tokens' => 0,
    ];

    protected function casts(): array
    {
        return ['context_closed_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<RegulatoryDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(RegulatoryDocument::class, 'regulatory_document_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->oldest();
    }

    /** @return HasOne<ChatMessage, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }
}
