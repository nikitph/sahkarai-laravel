<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\SendChatMessage;
use App\Ai\Agents\RegulatoryChatAgent;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;

class ChatStreamController extends Controller
{
    public function __invoke(Request $request, Chat $chat, SendChatMessage $messages): StreamableAgentResponse
    {
        $this->authorize('update', $chat);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'request_id' => ['required', 'uuid'],
        ]);
        $existing = $messages->prepare($request->user(), $chat, $validated['message'], $validated['request_id']);
        abort_if($existing?->role === 'assistant', 409, 'This message request was already completed.');

        $chat->unsetRelation('messages');

        return RegulatoryChatAgent::make($chat)
            ->stream(
                $validated['message'],
                provider: config('sahkarai.ai.provider'),
                model: config('sahkarai.ai.chat_model'),
                timeout: 120,
            )
            ->then(function (StreamedAgentResponse $response) use ($messages, $chat, $validated): void {
                $messages->complete(
                    $chat,
                    $validated['request_id'],
                    $response->text,
                    $response->usage->completionTokens,
                    $response->meta->model,
                    $response->meta->provider,
                    $response->usage->toArray(),
                );
            });
    }
}
