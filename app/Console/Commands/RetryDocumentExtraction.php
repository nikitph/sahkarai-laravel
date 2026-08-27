<?php

namespace App\Console\Commands;

use App\Jobs\Ingestion\ExtractDocumentText;
use App\Jobs\Ingestion\ExtractDocumentTextWithKimi;
use App\Models\DocumentVersion;
use Illuminate\Console\Command;

class RetryDocumentExtraction extends Command
{
    protected $signature = 'regulatory:extraction-retry
        {version : Document version ID}
        {--kimi : Skip native extraction and retry Kimi directly}
        {--sync : Run the selected extraction job synchronously}';

    protected $description = 'Retry a terminal regulatory-document extraction';

    public function handle(): int
    {
        $version = DocumentVersion::with('document')->find((int) $this->argument('version'));
        if (! $version) {
            $this->error('Document version not found.');

            return self::FAILURE;
        }
        if (! in_array($version->mime_type, ['application/pdf', 'text/html', 'text/plain'], true)) {
            $this->error('This version does not have a supported source document type.');

            return self::FAILURE;
        }
        if ($this->option('kimi') && ($version->document->isUserUpload() || $version->mime_type !== 'application/pdf')) {
            $this->error('Kimi retries are limited to platform-owned PDF versions.');

            return self::FAILURE;
        }
        if ($this->option('kimi') && (! config('sahkarai.ingestion.kimi.enabled') || blank(config('sahkarai.ingestion.kimi.api_key')))) {
            $this->error('Kimi extraction is disabled or KIMI_API_KEY is not configured.');

            return self::FAILURE;
        }

        $version->update([
            'status' => $this->option('kimi') ? 'kimi_pending' : 'pending',
            'extraction_status' => $this->option('kimi') ? 'kimi_pending' : 'pending',
            'extraction_error' => null,
            'needs_review_at' => null,
        ]);
        $job = $this->option('kimi')
            ? new ExtractDocumentTextWithKimi($version->getKey())
            : new ExtractDocumentText($version->getKey());
        $this->option('sync') ? dispatch_sync($job) : dispatch($job);
        $this->info("Extraction retry dispatched for document version {$version->getKey()}.");

        return self::SUCCESS;
    }
}
