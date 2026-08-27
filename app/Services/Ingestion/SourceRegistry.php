<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\SourceAdapter;
use App\Enums\RegulatorySource;

class SourceRegistry
{
    public function adapter(RegulatorySource $source): SourceAdapter
    {
        return match ($source) {
            RegulatorySource::Rbi => new RbiNotificationAdapter,
            RegulatorySource::Cbic => new CbicCircularAdapter,
            RegulatorySource::Nabard => new NabardCircularAdapter,
            RegulatorySource::IncomeTax, RegulatorySource::Gst => new ConfiguredFeedAdapter($source),
            RegulatorySource::UserUpload => throw new \InvalidArgumentException('User uploads do not have a polling adapter.'),
        };
    }
}
