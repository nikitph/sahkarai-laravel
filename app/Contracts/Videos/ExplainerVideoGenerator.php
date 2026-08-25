<?php

namespace App\Contracts\Videos;

use App\Data\RenderedExplainerVideo;

interface ExplainerVideoGenerator
{
    /** @param array<string, mixed> $lesson */
    public function generate(array $lesson, string $workingDirectory): RenderedExplainerVideo;
}
