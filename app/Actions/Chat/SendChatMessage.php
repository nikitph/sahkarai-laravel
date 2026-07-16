<?php

namespace App\Actions\Chat;

use App\Actions\Credits\AdjustCredits;
use App\Ai\Agents\RegulatoryChatAgent;
use App\Enums\CreditReason;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendChatMessage
{
    public function __construct(private readonly AdjustCredits $credits) {}

    public function handle(User $user, Chat $chat, string $content, string $requestId): ChatMessage
    {
        $existing = $this->prepare($user, $chat, $content, $requestId);
        if ($existing?->role === 'assistant') {
            return $existing;
        }

        $chat->unsetRelation('messages');
        $response = RegulatoryChatAgent::make($chat)->prompt(
            $content,
            provider: config('sahkarai.ai.provider'),
            model: config('sahkarai.ai.chat_model'),
            timeout: 120,
        );

        return $this->complete(
            $chat, $requestId, $response->text,
            $response->usage->completionTokens, $response->meta->model, $response->meta->provider,
            $response->usage->toArray(),
        );
    }

    public function prepare(User $user, Chat $chat, string $content, string $requestId): ?ChatMessage
    {
        $contextClosed = false;
        $result = DB::transaction(function () use ($user, $chat, $content, $requestId, &$contextClosed): ?ChatMessage {
            $lockedChat = Chat::query()->whereKey($chat->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($lockedChat->user_id === $user->getKey(), 403);

            $existing = ChatMessage::query()->where('request_id', $requestId)->first();
            if ($existing) {
                abort_unless($existing->chat_id === $lockedChat->getKey(), 409);
                abort_unless(hash_equals($existing->content, $content), 409, 'This request identifier belongs to a different message.');

                $assistant = $lockedChat->messages()->where('metadata->request_id', $requestId)->where('role', 'assistant')->first();
                if ($assistant) {
                    return $assistant;
                }
                abort_unless($lockedChat->status === 'active', 409, 'This incomplete message belongs to a closed chat.');

                return $existing;
            }

            $tokens = $this->estimateTokens($content);
            $threshold = (int) config('sahkarai.ai.context_window_tokens');
            if ($lockedChat->status !== 'active' || $lockedChat->context_tokens + $tokens > $threshold) {
                if ($lockedChat->status === 'active') {
                    $lockedChat->update(['status' => 'closed_context_full', 'context_closed_at' => now(), 'closed_at' => now()]);
                    $contextClosed = true;

                    return null;
                }
                throw ValidationException::withMessages(['message' => 'This chat has reached its context limit. Start a new chat to continue.']);
            }

            $message = $lockedChat->messages()->create([
                'role' => 'user',
                'content' => $content,
                'token_count' => $tokens,
                'request_id' => $requestId,
            ]);
            $this->credits->handle($user, -1, CreditReason::DebitMessage, "chat-message:{$requestId}", $message);
            $lockedChat->increment('context_tokens', $tokens);

            return null;
        }, attempts: 3);

        if ($contextClosed) {
            throw ValidationException::withMessages(['message' => 'This chat has reached its context limit. Start a new chat to continue.']);
        }

        return $result;
    }

    /** @param array<string, mixed> $usage */
    public function complete(Chat $chat, string $requestId, string $content, int $completionTokens, ?string $model, ?string $provider, array $usage): ChatMessage
    {
        $answerTokens = $completionTokens ?: $this->estimateTokens($content);

        return DB::transaction(function () use ($chat, $requestId, $content, $answerTokens, $model, $provider, $usage): ChatMessage {
            $lockedChat = Chat::query()->whereKey($chat->getKey())->lockForUpdate()->firstOrFail();
            $existing = $lockedChat->messages()->where('metadata->request_id', $requestId)->where('role', 'assistant')->first();
            if ($existing) {
                return $existing;
            }

            $message = $lockedChat->messages()->create([
                'role' => 'assistant',
                'content' => $content,
                'token_count' => $answerTokens,
                'model_id' => $model,
                'metadata' => [
                    'request_id' => $requestId,
                    'provider' => $provider,
                    'usage' => $usage,
                    'insight' => $this->extractInsight($content),
                ],
            ]);
            $lockedChat->increment('context_tokens', $answerTokens);
            if ($lockedChat->context_tokens > (int) config('sahkarai.ai.context_window_tokens')) {
                $lockedChat->update(['status' => 'closed_context_full', 'context_closed_at' => now(), 'closed_at' => now()]);
            }

            return $message;
        });
    }

    private function estimateTokens(string $content): int
    {
        return max(1, (int) ceil(mb_strlen($content) / 4));
    }

    /** @return array<string, mixed>|array<int, mixed>|null */
    private function extractInsight(string $content): ?array
    {
        $candidate = trim($content);
        if (preg_match('/```json\s*(.*?)\s*```/is', $content, $matches) === 1) {
            $candidate = $matches[1];
        }
        $decoded = json_decode($candidate, true);

        return is_array($decoded) ? $decoded : null;
    }
}
