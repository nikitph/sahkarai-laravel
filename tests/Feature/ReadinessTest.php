<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    public function test_readiness_checks_database_cache_and_regulatory_storage(): void
    {
        Storage::fake('local');
        config(['sahkarai.ingestion.storage_disk' => 'local']);

        $this->getJson('/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'checks' => [
                    'database' => true,
                    'cache' => true,
                    'storage' => true,
                ],
            ]);
    }
}
