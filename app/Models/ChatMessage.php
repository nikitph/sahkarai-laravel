<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_id
 * @property string $role
 * @property string $content
 * @property int $token_count
 * @property string|null $model_id
 * @property string|null $request_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
class ChatMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<Chat, $this> */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Chat messages are immutable.'));
        static::deleting(fn () => throw new \LogicException('Chat messages are immutable.'));
    }
}
