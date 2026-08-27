<?php

namespace App\Actions\Videos;

use App\Actions\Credits\AdjustCredits;
use App\Enums\CreditReason;
use App\Enums\ExplainerVideoStatus;
use App\Jobs\Videos\GenerateExplainerVideo;
use App\Models\DocumentVersion;
use App\Models\ExplainerVideo;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueExplainerVideo
{
    public function __construct(private readonly AdjustCredits $credits) {}

    public function forArchive(DocumentVersion $version): ExplainerVideo
    {
        if ($version->document()->whereNotNull('uploaded_by_user_id')->exists()) {
            throw ValidationException::withMessages(['video' => 'Private uploads require an explicit owner request.']);
        }

        return $this->queue($version, null, 'en');
    }

    public function forPrivateUpload(DocumentVersion $version, User $user, string $locale): ExplainerVideo
    {
        return $this->queue($version, $user, $locale);
    }

    private function queue(DocumentVersion $version, ?User $user, string $locale): ExplainerVideo
    {
        $video = DB::transaction(function () use ($version, $user, $locale): ExplainerVideo {
            $locked = DocumentVersion::query()
                ->with(['document', 'interpretation', 'explainerVideo'])
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if (! $locked->isPublished() || $locked->interpretation === null || ! isset($locked->interpretation->locale_payloads['en'])) {
                throw ValidationException::withMessages(['video' => 'The interpretation must be complete before generating a video.']);
            }

            $video = $locked->explainerVideo ?: $locked->explainerVideo()->create([
                'requested_by_user_id' => $user?->getKey(),
                'status' => ExplainerVideoStatus::Queued,
                'metadata' => ['locale' => $locale],
            ]);
            if (! $video->wasRecentlyCreated && ($video->status === ExplainerVideoStatus::Ready || in_array($video->status, [ExplainerVideoStatus::Queued, ExplainerVideoStatus::Generating], true))) {
                return $video;
            }

            $chargeSequence = (int) data_get($video->metadata, 'charge_sequence', 0);
            if ($user !== null) {
                $chargeSequence++;
                $this->credits->handle(
                    $user,
                    -Config::integer('sahkarai.video.credits'),
                    CreditReason::DebitExplainerVideo,
                    "explainer-video:{$locked->getKey()}:debit:{$chargeSequence}",
                    $video,
                    ['document_version_id' => $locked->getKey(), 'charge_sequence' => $chargeSequence],
                );
            }

            $video->update([
                'requested_by_user_id' => $user?->getKey(),
                'status' => ExplainerVideoStatus::Queued,
                'failure_code' => null,
                'failure_message' => null,
                'metadata' => [...($video->metadata ?? []), 'locale' => $locale, 'charge_sequence' => $chargeSequence],
            ]);
            $locked->update(['video_status' => ExplainerVideoStatus::Queued->value]);

            return $video;
        }, attempts: 3);

        if ($video->wasRecentlyCreated || $video->wasChanged('status')) {
            GenerateExplainerVideo::dispatch($video->getKey())
                ->onConnection(config('sahkarai.video.queue_connection'))
                ->onQueue(config('sahkarai.video.queue'))
                ->afterCommit();
        }

        return $video;
    }
}
