<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReadinessController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => fn (): bool => (int) DB::selectOne('select 1 as ready')?->ready === 1,
            'cache' => function (): bool {
                $key = 'health:'.Str::uuid();
                Cache::put($key, 'ready', 10);
                $ready = Cache::pull($key);

                return $ready === 'ready';
            },
            'storage' => function (): bool {
                $disk = Storage::disk(config('sahkarai.ingestion.storage_disk'));
                $path = 'health/'.Str::uuid().'.txt';

                try {
                    return $disk->put($path, 'ready') && $disk->get($path) === 'ready';
                } finally {
                    $disk->delete($path);
                }
            },
        ];

        $results = [];

        foreach ($checks as $name => $check) {
            try {
                $results[$name] = $check();
            } catch (Throwable) {
                $results[$name] = false;
            }
        }

        $ready = ! in_array(false, $results, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'unavailable',
            'checks' => $results,
        ], $ready ? 200 : 503);
    }
}
