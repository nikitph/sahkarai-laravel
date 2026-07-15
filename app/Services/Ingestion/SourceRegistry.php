<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\SourceAdapter;
use App\Enums\RegulatorySource;

class SourceRegistry
{
    public function adapter(RegulatorySource $source): SourceAdapter
    {
        return new ConfiguredFeedAdapter($source);
    }
}
