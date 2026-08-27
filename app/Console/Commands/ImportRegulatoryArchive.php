<?php

namespace App\Console\Commands;

use App\Actions\Ingestion\StoreAcquiredDocument;
use App\Data\DocumentCandidate;
use App\Enums\RegulatorySource;
use App\Jobs\Ingestion\ExtractDocumentText;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;
use SplFileObject;
use Throwable;

class ImportRegulatoryArchive extends Command
{
    protected $signature = 'regulatory:archive-import
        {path : Directory containing per-source manifest.jsonl files}
        {--source=* : Import only these source names}
        {--year=* : Import only these publication years}
        {--limit= : Stop after this many valid manifest rows}
        {--dry-run : Validate manifests and files without writing}
        {--sync : Run native extraction synchronously after each imported version}
        {--report= : Write the reconciliation report as JSON}';

    protected $description = 'Idempotently import the immutable regulatory PDF archive';

    public function handle(StoreAcquiredDocument $store): int
    {
        $root = realpath((string) $this->argument('path'));
        if ($root === false || ! is_dir($root)) {
            $this->error('Archive path does not exist or is not a directory.');

            return self::FAILURE;
        }

        $manifests = glob($root.'/*/manifest.jsonl') ?: [];
        sort($manifests);
        if ($manifests === []) {
            $this->error('No per-source manifest.jsonl files were found.');

            return self::FAILURE;
        }

        $sources = array_values(array_filter(array_map('strval', (array) $this->option('source'))));
        $years = array_values(array_filter(array_map('intval', (array) $this->option('year'))));
        $limit = $this->option('limit') === null ? null : max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $sync = (bool) $this->option('sync');
        /** @var array<string, array{validated: int, created: int, unchanged: int, failed: int}> $sourceStats */
        $sourceStats = [];
        $report = [
            'archive_path' => $root,
            'dry_run' => $dryRun,
            'examined' => 0,
            'validated' => 0,
            'created' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($manifests as $manifest) {
            $manifestSource = basename(dirname($manifest));
            if ($sources !== [] && ! in_array($manifestSource, $sources, true)) {
                continue;
            }

            $file = new SplFileObject($manifest, 'rb');
            $lineNumber = 0;
            while (! $file->eof()) {
                $line = trim((string) $file->fgets());
                $lineNumber++;
                if ($line === '') {
                    continue;
                }
                if ($limit !== null && $report['validated'] >= $limit) {
                    break 2;
                }

                $report['examined']++;
                try {
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (! is_array($row)) {
                        throw new RuntimeException('Manifest row is not an object.');
                    }
                    if (($row['status'] ?? null) !== 'downloaded') {
                        throw new RuntimeException('Manifest row is not marked downloaded.');
                    }

                    $source = RegulatorySource::from((string) ($row['source'] ?? $manifestSource));
                    if ($source->value !== $manifestSource) {
                        throw new RuntimeException('Manifest source does not match its directory.');
                    }
                    $year = (int) ($row['year'] ?? 0);
                    if ($years !== [] && ! in_array($year, $years, true)) {
                        continue;
                    }

                    $relativePath = ltrim((string) ($row['relative_path'] ?? ''), '/');
                    $path = realpath($root.'/'.$relativePath);
                    if ($path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
                        throw new RuntimeException('Manifest PDF path is missing or escapes the archive root.');
                    }

                    $contents = file_get_contents($path);
                    if ($contents === false || ! str_starts_with(ltrim($contents), '%PDF-')) {
                        throw new RuntimeException('Archive file is not a valid PDF payload.');
                    }

                    foreach (['source_id', 'title', 'source_url', 'download_url', 'published_at', 'sha256', 'bytes'] as $field) {
                        if (! array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                            throw new RuntimeException("Manifest field {$field} is required.");
                        }
                    }

                    $candidate = new DocumentCandidate(
                        source: $source,
                        sourceDocumentId: trim((string) $row['source_id']),
                        title: trim((string) $row['title']),
                        downloadUrl: (string) $row['download_url'],
                        sourceUrl: (string) $row['source_url'],
                        publishedAt: CarbonImmutable::parse((string) $row['published_at']),
                        isBackfill: true,
                    );
                    $report['validated']++;
                    $sourceStats[$source->value] ??= ['validated' => 0, 'created' => 0, 'unchanged' => 0, 'failed' => 0];
                    $sourceStats[$source->value]['validated']++;

                    if ($dryRun) {
                        $this->validateManifestIntegrity($contents, $row);

                        continue;
                    }

                    $version = $store->handle(
                        candidate: $candidate,
                        contents: $contents,
                        mime: 'application/pdf',
                        originalFilename: basename($path),
                        expectedSha256: (string) $row['sha256'],
                        expectedBytes: (int) $row['bytes'],
                        dispatchExtraction: ! $sync,
                    );
                    $outcome = $version ? 'created' : 'unchanged';
                    $report[$outcome]++;
                    $sourceStats[$source->value][$outcome]++;

                    if ($version && $sync) {
                        ExtractDocumentText::dispatchSync($version->getKey());
                    }
                } catch (Throwable $exception) {
                    $report['failed']++;
                    $report['failures'][] = [
                        'manifest' => $manifest,
                        'line' => $lineNumber,
                        'error' => $exception->getMessage(),
                    ];
                    $sourceKey = $manifestSource;
                    $sourceStats[$sourceKey] ??= ['validated' => 0, 'created' => 0, 'unchanged' => 0, 'failed' => 0];
                    $sourceStats[$sourceKey]['failed']++;
                    $this->warn("{$manifestSource}:{$lineNumber}: {$exception->getMessage()}");
                }
            }
        }

        $report['sources'] = $sourceStats;

        $this->table(
            ['Examined', 'Validated', 'Created', 'Unchanged', 'Failed'],
            [[$report['examined'], $report['validated'], $report['created'], $report['unchanged'], $report['failed']]],
        );

        if (is_string($this->option('report')) && $this->option('report') !== '') {
            $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            if (file_put_contents((string) $this->option('report'), $encoded) === false) {
                $this->error('Unable to write the reconciliation report.');

                return self::FAILURE;
            }
        }

        return $report['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, mixed> $row */
    private function validateManifestIntegrity(string $contents, array $row): void
    {
        if (strlen($contents) !== (int) $row['bytes']) {
            throw new RuntimeException('Archive file size does not match the manifest.');
        }
        if (! hash_equals(strtolower((string) $row['sha256']), hash('sha256', $contents))) {
            throw new RuntimeException('Archive file checksum does not match the manifest.');
        }
    }
}
