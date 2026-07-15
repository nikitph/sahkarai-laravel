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
        if ($existing) {
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
        $existing = ChatMessage::query()->where('request_id', $requestId)->first();
        if ($existing) {
            abort_unless($existing->chat_id === $chat->getKey(), 409);

            return $chat->messages()->where('metadata->request_id', $requestId)->where('role', 'assistant')->first() ?? $existing;
        }

        $tokens = $this->estimateTokens($content);
        $threshold = (int) config('sahkarai.ai.context_window_tokens');
        if ($chat->status !== 'active' || $chat->context_tokens + $tokens > $threshold) {
            if ($chat->status === 'active') {
                $chat->update(['status' => 'closed_context_full', 'context_closed_at' => now(), 'closed_at' => now()]);
            }
            throw ValidationException::withMessages(['message' => 'This chat has reached its context limit. Start a new chat to continue.']);
        }

        DB::transaction(function () use ($user, $chat, $content, $requestId, $tokens): void {
            $message = $chat->messages()->create([
                'role' => 'user',
                'content' => $content,
                'token_count' => $tokens,
                'request_id' => $requestId,
            ]);
            $this->credits->handle($user, -1, CreditReason::DebitMessage, "chat-message:{$requestId}", $message);
            $chat->increment('context_tokens', $tokens);
        }, attempts: 3);

        return null;
    }

    /** @param array<string, mixed> $usage */
    public function complete(Chat $chat, string $requestId, string $content, int $completionTokens, ?string $model, ?string $provider, array $usage): ChatMessage
    {
        $existing = $chat->messages()->where('metadata->request_id', $requestId)->where('role', 'assistant')->first();
        if ($existing) {
            return $existing;
        }
        $answerTokens = $completionTokens ?: $this->estimateTokens($content);

        return DB::transaction(function () use ($chat, $requestId, $content, $answerTokens, $model, $provider, $usage): ChatMessage {
            $message = $chat->messages()->create([
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
            $chat->increment('context_tokens', $answerTokens);
            if ($chat->fresh()->context_tokens > (int) config('sahkarai.ai.context_window_tokens')) {
                $chat->update(['status' => 'closed_context_full', 'context_closed_at' => now(), 'closed_at' => now()]);
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
