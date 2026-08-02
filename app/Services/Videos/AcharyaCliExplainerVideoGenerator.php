<?php

namespace App\Services\Videos;

use App\Contracts\Videos\ExplainerVideoGenerator;
use App\Data\RenderedExplainerVideo;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class AcharyaCliExplainerVideoGenerator implements ExplainerVideoGenerator
{
    public function generate(array $lesson, string $workingDirectory): RenderedExplainerVideo
    {
        File::ensureDirectoryExists($workingDirectory);
        $lessonPath = $workingDirectory.'/lesson.json';
        $videoPath = $workingDirectory.'/final.mp4';
        $manifestPath = $workingDirectory.'/build-manifest.json';
        File::put($lessonPath, json_encode($lesson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        $process = new Process([
            (string) config('sahkarai.video.node_binary'),
            base_path('video-pipeline/render.mjs'),
            '--lesson', $lessonPath,
            '--output', $workingDirectory,
            '--quality', (string) config('sahkarai.video.quality'),
            '--fps', (string) config('sahkarai.video.fps'),
        ], base_path('video-pipeline'), [
            'ELEVENLABS_API_KEY' => (string) config('sahkarai.video.elevenlabs.api_key'),
            'HYPERFRAMES_NO_UPDATE_CHECK' => '1',
            'CI' => '1',
        ]);
        $process->setTimeout((float) config('sahkarai.video.render_timeout'));
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Acharya renderer failed: '.str($process->getErrorOutput() ?: $process->getOutput())->trim()->limit(4000));
        }
        if (! is_file($videoPath) || ! is_file($manifestPath)) {
            throw new RuntimeException('Acharya renderer completed without the required video and manifest artifacts.');
        }

        $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        return new RenderedExplainerVideo(
            videoPath: $videoPath,
            manifestPath: $manifestPath,
            durationMs: (int) ($manifest['durationMs'] ?? 0),
            inputHash: (string) ($manifest['inputHash'] ?? hash_file('sha256', $lessonPath)),
            pipelineVersion: (string) ($manifest['pipelineVersion'] ?? 'sahkar-acharya/unknown'),
            metadata: ['renderer_output' => trim($process->getOutput())],
        );
    }
}
