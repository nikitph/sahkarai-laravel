<?php

namespace App\Data;

final readonly class KimiExtractionResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $text,
        public string $requestId,
        public array $metadata = [],
    ) {}
}
