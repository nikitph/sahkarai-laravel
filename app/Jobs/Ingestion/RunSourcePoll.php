<?php

namespace App\Jobs\Ingestion;

use App\Actions\Ingestion\AcquireDocument;
use App\Data\DocumentCandidate;
use App\Enums\RegulatorySource;
use App\Models\OpsAlert;
use App\Models\PollRun;
use App\Services\Ingestion\SourceRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunSourcePoll implements ShouldQueue
{
    use Queueable;

    // HTTP clients already retry transient transport failures. A poll run itself
    // is attempted once so queue retries cannot masquerade as consecutive runs.
    public int $tries = 1;

    public function __construct(public readonly RegulatorySource $source, public readonly string $kind = 'scheduled') {}

    public function handle(SourceRegistry $registry, AcquireDocument $acquire): void
    {
        $errors = [];
        $run = PollRun::create([
            'source' => $this->source,
            'kind' => $this->kind,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            foreach ($registry->adapter($this->source)->discover() as $candidate) {
                $run->increment('discovered_count');
                if ($invalidField = $this->invalidField($candidate)) {
                    $run->increment('failed_count');
                    $errors[] = "candidate_invalid:{$invalidField}";

                    continue;
                }

                if ($this->kind === 'backfill') {
                    if ($candidate->publishedAt?->lt(now()->subMonths((int) config('sahkarai.ingestion.backfill_months')))) {
                        continue;
                    }
                    $candidate = new DocumentCandidate(
                        source: $candidate->source,
                        sourceDocumentId: $candidate->sourceDocumentId,
                        title: $candidate->title,
                        downloadUrl: $candidate->downloadUrl,
                        sourceUrl: $candidate->sourceUrl,
                        documentType: $candidate->documentType,
                        applicability: $candidate->applicability,
                        publishedAt: $candidate->publishedAt,
                        effectiveAt: $candidate->effectiveAt,
                        isBackfill: true,
                    );
                }
                try {
                    if ($acquire->handle($candidate)) {
                        $run->increment('created_count');
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $run->increment('failed_count');
                    $errors[] = "acquisition_failed:{$candidate->sourceDocumentId}: {$exception->getMessage()}";
                }
            }

            $run->refresh();
            $run->update([
                'status' => $run->failed_count > 0 ? 'partial' : 'ok',
                'error' => $errors === [] ? null : implode("\n", $errors),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => $exception->getMessage(), 'completed_at' => now()]);
            $this->raiseConsecutiveFailureAlert($run);
            throw $exception;
        }
    }

    private function raiseConsecutiveFailureAlert(PollRun $run): void
    {
        $recent = PollRun::query()
            ->where('source', $this->source)
            ->where('kind', $this->kind)
            ->latest('started_at')
            ->limit(3)
            ->pluck('status');

        if ($recent->count() === 3 && $recent->every(fn (string $status) => $status === 'failed')) {
            OpsAlert::query()->firstOrCreate(
                [
                    'type' => 'source_poll_failed',
                    'title' => strtoupper($this->source->value).' source polling failed three consecutive times',
                    'resolved_at' => null,
                ],
                [
                    'severity' => 'critical',
                    'details' => $run->error,
                    'context' => [
                        'tag' => "ingestion.{$this->source->value}.three_consecutive_failures",
                        'source' => $this->source->value,
                        'poll_run_id' => $run->getKey(),
                    ],
                ],
            );
        }
    }

    private function invalidField(DocumentCandidate $candidate): ?string
    {
        return match (true) {
            trim($candidate->sourceDocumentId) === '' => 'source_document_id',
            trim($candidate->title) === '' => 'title',
            $candidate->sourceUrl === null || trim($candidate->sourceUrl) === '' => 'source_url',
            $candidate->publishedAt === null => 'published_date',
            $candidate->documentType === null => 'document_type',
            trim($candidate->downloadUrl) === '' => 'download_handle',
            default => null,
        };
    }
}
