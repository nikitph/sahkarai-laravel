<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $user_id @property int $regulatory_document_id */
class DocumentView extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_viewed_at' => 'datetime'];
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
}
