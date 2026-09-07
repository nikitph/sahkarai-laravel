<?php

namespace App\Jobs\Videos;

use App\Actions\Credits\AdjustCredits;
use App\Actions\Videos\BuildExplainerLesson;
use App\Contracts\Videos\ExplainerVideoGenerator;
use App\Enums\CreditReason;
use App\Enums\ExplainerVideoStatus;
use App\Models\ExplainerVideo;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateExplainerVideo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    /** @var array<int, int> */
    public array $backoff = [120];

    public function __construct(public readonly int $explainerVideoId) {}

    public function uniqueId(): string
    {
        return (string) $this->explainerVideoId;
    }

    public function handle(BuildExplainerLesson $buildLesson, ExplainerVideoGenerator $generator): void
    {
        if (! Config::boolean('sahkarai.video.enabled')) {
            $video = ExplainerVideo::query()->with('version')->find($this->explainerVideoId);
            if ($video !== null && $video->status !== ExplainerVideoStatus::Ready) {
                $video->update([
                    'status' => ExplainerVideoStatus::Failed,
                    'failure_code' => 'feature_disabled',
                    'failure_message' => 'Explainer video generation is temporarily disabled.',
                ]);
                $video->version->update(['video_status' => ExplainerVideoStatus::Failed->value]);
            }

            return;
        }

        $video = ExplainerVideo::query()->with(['version.document', 'version.interpretation'])->findOrFail($this->explainerVideoId);
        if ($video->status === ExplainerVideoStatus::Ready) {
            return;
        }

        $video->update([
            'status' => ExplainerVideoStatus::Generating,
            'attempts' => $video->attempts + 1,
            'started_at' => $video->started_at ?? now(),
            'failure_code' => null,
            'failure_message' => null,
        ]);
        $video->version->update(['video_status' => ExplainerVideoStatus::Generating->value]);
        $workingDirectory = storage_path("app/video-work/{$video->getKey()}-".str()->uuid());

        try {
            $lesson = $buildLesson->handle($video->version, (string) data_get($video->metadata, 'locale', 'en'));
            $rendered = $generator->generate($lesson, $workingDirectory);
            $diskName = (string) config('sahkarai.video.storage_disk');
            $prefix = trim((string) config('sahkarai.video.storage_prefix'), '/').'/'.$video->version->getKey().'/'.$rendered->inputHash;
            $videoPath = "{$prefix}/explainer.mp4";
            $manifestPath = "{$prefix}/build-manifest.json";
            $disk = Storage::disk($diskName);
            $videoStream = fopen($rendered->videoPath, 'rb');
            $manifestStream = fopen($rendered->manifestPath, 'rb');
            if ($videoStream === false || $manifestStream === false || ! $disk->put($videoPath, $videoStream) || ! $disk->put($manifestPath, $manifestStream)) {
                throw new RuntimeException('Unable to persist generated explainer video artifacts.');
            }

            $video->update([
                'status' => ExplainerVideoStatus::Ready,
                'storage_disk' => $diskName,
                'video_path' => $videoPath,
                'manifest_path' => $manifestPath,
                'mime_type' => 'video/mp4',
                'size_bytes' => $disk->size($videoPath),
                'duration_ms' => $rendered->durationMs,
                'input_hash' => $rendered->inputHash,
                'pipeline_version' => $rendered->pipelineVersion,
                'metadata' => [...($video->metadata ?? []), ...$rendered->metadata],
                'completed_at' => now(),
            ]);
            $video->version->update(['video_status' => ExplainerVideoStatus::Ready->value]);
        } finally {
            File::deleteDirectory($workingDirectory);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $video = ExplainerVideo::query()->with(['version', 'requestedBy'])->find($this->explainerVideoId);
        if ($video === null || $video->status === ExplainerVideoStatus::Ready) {
            return;
        }

        $video->update([
            'status' => ExplainerVideoStatus::Failed,
            'failure_code' => 'render_failed',
            'failure_message' => str($exception?->getMessage() ?? 'The video renderer failed.')->limit(4000)->toString(),
        ]);
        $video->version->update(['video_status' => ExplainerVideoStatus::Failed->value]);
        if ($video->requestedBy !== null) {
            $chargeSequence = (int) data_get($video->metadata, 'charge_sequence', 1);
            app(AdjustCredits::class)->handle(
                $video->requestedBy,
                Config::integer('sahkarai.video.credits'),
                CreditReason::RefundExplainerVideo,
                "explainer-video:{$video->document_version_id}:refund:{$chargeSequence}",
                $video,
                ['failure_code' => 'render_failed', 'charge_sequence' => $chargeSequence],
            );
        }
    }
}
