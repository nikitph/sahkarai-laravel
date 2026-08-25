<?php

namespace App\Data;

class RenderedExplainerVideo
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $videoPath,
        public readonly string $manifestPath,
        public readonly int $durationMs,
        public readonly string $inputHash,
        public readonly string $pipelineVersion,
        public readonly array $metadata = [],
    ) {}
}
