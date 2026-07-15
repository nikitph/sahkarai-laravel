<?php

namespace App\Ai\Agents;

use App\Models\Chat;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class RegulatoryChatAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(private readonly Chat $chat) {}

    public function instructions(): Stringable|string
    {
        $document = $this->chat->document;
        $source = $this->chat->version->sourceText();

        return "You answer questions only about the regulatory document titled '{$document->title}'. "
            .'Use the source below as the authority. Clearly say when the answer is absent or uncertain. '
            ."Never claim to provide legal advice.\n\nSOURCE DOCUMENT:\n".$source;
    }

    public function messages(): iterable
    {
        $messages = $this->chat->messages;

        return $messages->slice(0, max(0, $messages->count() - 1))->map(fn ($message) => $message->role === 'user'
            ? new UserMessage($message->content)
            : new AssistantMessage($message->content));
    }
}
