<?php

namespace Tests\Unit;

use App\Actions\Interpretations\NormalizeInterpretationDates;
use PHPUnit\Framework\TestCase;

class NormalizeInterpretationDatesTest extends TestCase
{
    public function test_it_normalizes_unambiguous_regulatory_dates_before_validation(): void
    {
        $payload = (new NormalizeInterpretationDates)->handle([
            'deadlines' => [
                ['due_date' => '31.12.2026', 'description' => 'Dispose of applications.'],
                ['due_date' => '30/09/2025', 'description' => 'Original filing deadline.'],
            ],
            'effective_date' => '02-07-2026',
        ]);

        $this->assertSame('2026-12-31', $payload['deadlines'][0]['due_date']);
        $this->assertSame('2025-09-30', $payload['deadlines'][1]['due_date']);
        $this->assertSame('2026-07-02', $payload['effective_date']);
    }

    public function test_it_leaves_ambiguous_or_already_normalized_values_unchanged(): void
    {
        $payload = (new NormalizeInterpretationDates)->handle([
            'deadlines' => [
                ['due_date' => '2026-12-31', 'description' => 'Already ISO.'],
                ['due_date' => '01.10.2025 to 31.03.2026', 'description' => 'A range is invalid.'],
            ],
            'effective_date' => null,
        ]);

        $this->assertSame('2026-12-31', $payload['deadlines'][0]['due_date']);
        $this->assertSame('01.10.2025 to 31.03.2026', $payload['deadlines'][1]['due_date']);
        $this->assertNull($payload['effective_date']);
    }
}
