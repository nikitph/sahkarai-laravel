<?php

namespace App\Http\Controllers\Archive;

use App\Actions\Videos\QueueExplainerVideo;
use App\Enums\ExplainerVideoStatus;
use App\Enums\SupportedLocale;
use App\Http\Controllers\Controller;
use App\Models\ExplainerVideo;
use App\Models\RegulatoryDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExplainerVideoController extends Controller
{
    public function store(Request $request, RegulatoryDocument $document, QueueExplainerVideo $queue): RedirectResponse
    {
        $this->authorize('generateExplainerVideo', $document);
        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'locale' => ['nullable', Rule::enum(SupportedLocale::class)],
        ]);
        $version = $document->versions()->whereKey($validated['version'])->firstOrFail();
        $queue->forPrivateUpload($version, $request->user(), $validated['locale'] ?? $request->user()->locale->value);

        return back()->with('success', 'Explainer video generation has been queued.');
    }

    public function show(Request $request, RegulatoryDocument $document, ExplainerVideo $video): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_unless(config('sahkarai.video.enabled'), 404);
        abort_unless($request->user()->canUseExplainerVideos(), 403);
        $version = $video->version()->where('regulatory_document_id', $document->getKey())->first();
        abort_unless($version && $document->isVersionVisibleTo($version, $request->user()), 404);
        abort_unless($video->status === ExplainerVideoStatus::Ready && $video->storage_disk && $video->video_path, 404);

        return Storage::disk($video->storage_disk)->response(
            $video->video_path,
            "sahkarai-explainer-{$document->getKey()}.mp4",
            ['Content-Type' => $video->mime_type ?? 'video/mp4', 'Cache-Control' => 'private, max-age=3600'],
        );
    }
}
