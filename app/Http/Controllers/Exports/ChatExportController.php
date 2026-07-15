<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatExportController extends Controller
{
    public function __invoke(Request $request, Chat $chat, string $format): Response
    {
        $this->authorize('view', $chat);
        abort_unless(in_array($format, ['json', 'md', 'pdf'], true), 422, 'export_format_invalid');
        $chat->load(['document', 'version', 'messages']);
        $payload = $this->payload($chat);
        $name = "sahkarai-chat-{$chat->getKey()}";

        if ($format === 'json') {
            return response()->json($payload, headers: ['Content-Disposition' => "attachment; filename={$name}.json"]);
        }

        $markdown = view('exports.chat-markdown', $payload)->render();
        if ($format === 'md') {
            return response($markdown, 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Content-Disposition' => "attachment; filename={$name}.md"]);
        }

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml(view('exports.chat-pdf', $payload)->render());
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename={$name}.pdf"]);
    }

    /** @return array<string, mixed> */
    private function payload(Chat $chat): array
    {
        return [
            'chat' => [
                'id' => $chat->getKey(), 'owner_id' => $chat->user_id, 'document_version_id' => $chat->document_version_id,
                'locale' => $chat->locale, 'status' => $chat->status, 'created_at' => $chat->created_at, 'closed_at' => $chat->closed_at,
            ],
            'document_version' => [
                'id' => $chat->version->getKey(), 'version' => $chat->version->version, 'source' => $chat->document->source,
                'source_document_id' => $chat->document->source_document_id, 'title' => $chat->document->title,
            ],
            'messages' => $chat->messages->map->only(['id', 'role', 'content', 'created_at']),
            'insights' => $chat->messages->pluck('metadata.insight')->filter()->values(),
        ];
    }
}
