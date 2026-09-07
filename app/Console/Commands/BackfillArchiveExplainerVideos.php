<?php

namespace App\Console\Commands;

use App\Actions\Videos\QueueExplainerVideo;
use App\Models\DocumentVersion;
use Illuminate\Console\Command;

class BackfillArchiveExplainerVideos extends Command
{
    protected $signature = 'sahkarai:videos:backfill {--limit=100}';

    protected $description = 'Queue missing explainer videos for interpreted public archive versions';

    public function handle(QueueExplainerVideo $queue): int
    {
        if (! config('sahkarai.video.enabled')) {
            $this->components->warn('Explainer video generation is temporarily disabled.');

            return self::SUCCESS;
        }

        $count = 0;
        DocumentVersion::query()
            ->whereIn('interpretation_status', ['published', 'partial'])
            ->whereIn('video_status', ['not_requested', 'failed'])
            ->whereHas('document', fn ($query) => $query->whereNull('uploaded_by_user_id'))
            ->with(['document', 'interpretation'])
            ->limit((int) $this->option('limit'))
            ->each(function (DocumentVersion $version) use ($queue, &$count): void {
                $queue->forArchive($version);
                $count++;
            });

        $this->info("Queued {$count} archive explainer video(s).");

        return self::SUCCESS;
    }
}
