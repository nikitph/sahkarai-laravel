<?php

namespace App\Console\Commands;

use App\Services\Ingestion\KimiFileExtractor;
use Illuminate\Console\Command;

class VerifyKimiOcr extends Command
{
    protected $signature = 'sahkarai:kimi:verify {pdf : Path to a representative image-only PDF}';

    protected $description = 'Upload, extract, and delete one PDF to verify the configured Kimi Files API';

    public function handle(KimiFileExtractor $extractor): int
    {
        $path = realpath((string) $this->argument('pdf'));
        if ($path === false || ! is_file($path)) {
            $this->error('The PDF path does not exist.');

            return self::FAILURE;
        }
        $contents = file_get_contents($path);
        if ($contents === false || ! str_starts_with(ltrim($contents), '%PDF-')) {
            $this->error('The verification file is not a PDF.');

            return self::FAILURE;
        }

        $result = $extractor->extract($contents, basename($path));
        if (blank($result->text)) {
            $this->error('Kimi returned no extracted text.');

            return self::FAILURE;
        }

        $this->info('Kimi file extraction verified; '.mb_strlen($result->text).' characters returned and the remote file was deleted.');

        return self::SUCCESS;
    }
}
