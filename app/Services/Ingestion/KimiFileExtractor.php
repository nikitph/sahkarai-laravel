<?php

namespace App\Services\Ingestion;

use App\Data\KimiExtractionResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class KimiFileExtractor
{
    public function extract(string $contents, string $filename): KimiExtractionResult
    {
        $apiKey = (string) config('sahkarai.ingestion.kimi.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Kimi OCR is enabled but KIMI_API_KEY is not configured.');
        }

        $fileId = null;

        try {
            $upload = $this->request()
                ->attach('file', $contents, $filename, ['Content-Type' => 'application/pdf'])
                ->post($this->url('/files'), ['purpose' => 'file-extract'])
                ->throw();

            $fileId = (string) $upload->json('id');
            if ($fileId === '') {
                throw new RuntimeException('Kimi did not return a file identifier.');
            }

            $content = $this->request()
                ->accept('text/plain')
                ->get($this->url("/files/{$fileId}/content"))
                ->throw()
                ->body();

            $this->request()
                ->retry(2, 500)
                ->delete($this->url("/files/{$fileId}"))
                ->throw();
            $fileId = null;

            return new KimiExtractionResult(
                text: $content,
                requestId: (string) ($upload->header('x-request-id') ?: $upload->json('id')),
                metadata: [
                    'remote_status' => $upload->json('status'),
                    'remote_bytes' => $upload->json('bytes'),
                    'remote_file_deleted' => true,
                ],
            );
        } finally {
            if ($fileId !== null) {
                try {
                    $this->request()->retry(2, 500)->delete($this->url("/files/{$fileId}"))->throw();
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('sahkarai.ingestion.kimi.api_key'))
            ->timeout((int) config('sahkarai.ingestion.kimi.timeout'))
            ->retry(2, 500);
    }

    private function url(string $path): string
    {
        return config('sahkarai.ingestion.kimi.base_url').$path;
    }
}
