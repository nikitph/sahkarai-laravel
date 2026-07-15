<?php

namespace App\Models;

use App\Enums\RegulatorySource;
use Illuminate\Database\Eloquent\Model;

class PollRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source' => RegulatorySource::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
