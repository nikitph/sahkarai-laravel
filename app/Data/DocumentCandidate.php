<?php

namespace App\Data;

use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use Carbon\CarbonImmutable;

final readonly class DocumentCandidate
{
    public function __construct(
        public RegulatorySource $source,
        public string $sourceDocumentId,
        public string $title,
        public string $downloadUrl,
        public ?string $sourceUrl = null,
        public DocumentType $documentType = DocumentType::Other,
        public Applicability $applicability = Applicability::Generic,
        public ?CarbonImmutable $publishedAt = null,
        public ?CarbonImmutable $effectiveAt = null,
        public bool $isBackfill = false,
    ) {}
}
