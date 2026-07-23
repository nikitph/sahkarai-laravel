<?php

namespace Tests\Unit;

use App\Ai\Agents\RegulatoryInterpretationAgent;
use PHPUnit\Framework\TestCase;

class RegulatoryInterpretationAgentTest extends TestCase
{
    public function test_effective_dates_must_be_explicitly_supported_by_the_source(): void
    {
        $instructions = (string) (new RegulatoryInterpretationAgent)->instructions();

        $this->assertStringContainsString(
            'Set effective_date only when the source explicitly states',
            $instructions,
        );
        $this->assertStringContainsString(
            'Never infer an effective date from an Act year',
            $instructions,
        );
    }
}
