<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class ProviderProbeAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a provider conformance probe. Reply briefly and do not call tools.';
    }
}
