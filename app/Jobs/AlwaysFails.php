<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class AlwaysFails implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function handle(): void
    {
        throw new RuntimeException('Intentional retry conformance failure.');
    }
}
