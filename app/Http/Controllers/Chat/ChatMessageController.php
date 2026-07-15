<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\SendChatMessage;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChatMessageController extends Controller
{
    public function store(Request $request, Chat $chat, SendChatMessage $send): RedirectResponse
    {
        $this->authorize('update', $chat);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'request_id' => ['required', 'uuid'],
        ]);
        $send->handle($request->user(), $chat, $validated['message'], $validated['request_id']);

        return back();
    }
}
