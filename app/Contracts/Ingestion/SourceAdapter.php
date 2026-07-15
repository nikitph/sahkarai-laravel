<?php

namespace App\Contracts\Ingestion;

use App\Data\DocumentCandidate;
use App\Enums\RegulatorySource;

interface SourceAdapter
{
    public function source(): RegulatorySource;

    /** @return iterable<DocumentCandidate> */
    public function discover(): iterable;
}
