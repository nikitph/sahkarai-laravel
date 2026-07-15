<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\RegulatoryDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Chat::class);

        return Inertia::render('chat/index', [
            'chats' => $request->user()->chats()->with(['document:id,title,source', 'latestMessage:id,chat_id,content'])->latest('updated_at')->paginate(20),
            'credits' => $request->user()->credits_balance,
        ]);
    }

    public function store(Request $request, RegulatoryDocument $document): RedirectResponse
    {
        $this->authorize('create', Chat::class);
        $validated = $request->validate(['version' => ['nullable', 'integer', 'min:1']]);
        $version = isset($validated['version'])
            ? $document->versions()->whereKey($validated['version'])->whereNotNull('extracted_text')->firstOrFail()
            : $document->latestVersion()->whereNotNull('extracted_text')->firstOrFail();
        $chat = $request->user()->chats()->create([
            'regulatory_document_id' => $document->getKey(),
            'document_version_id' => $version->getKey(),
            'title' => $document->title,
            'locale' => $request->user()->locale,
        ]);

        return to_route('chats.show', $chat);
    }

    public function show(Request $request, Chat $chat): Response
    {
        $this->authorize('view', $chat);
        $chat->load(['document:id,title,source,document_type,published_at', 'version:id,version', 'messages']);

        return Inertia::render('chat/show', [
            'chat' => $chat,
            'credits' => $request->user()->credits_balance,
            'contextLimit' => (int) config('sahkarai.ai.context_window_tokens'),
            'creditsResetAt' => $request->user()->credits_reset_at?->toDateString(),
            'topupUrl' => config('sahkarai.credits.topup_url'),
        ]);
    }

    public function close(Request $request, Chat $chat): RedirectResponse
    {
        $this->authorize('update', $chat);
        $chat->update(['status' => 'closed_by_user', 'closed_at' => now()]);

        return back()->with('success', 'Chat closed. You can still read or export it.');
    }

    public function restart(Request $request, Chat $chat): RedirectResponse
    {
        $this->authorize('view', $chat);
        $this->authorize('create', Chat::class);
        abort_unless($chat->status === 'closed_context_full', 422);
        $fresh = $request->user()->chats()->create([
            'regulatory_document_id' => $chat->regulatory_document_id,
            'document_version_id' => $chat->document_version_id,
            'title' => $chat->title,
            'locale' => $request->user()->locale,
        ]);

        return to_route('chats.show', $fresh);
    }
}
