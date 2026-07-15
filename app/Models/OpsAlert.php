<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpsAlert extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'array', 'resolved_at' => 'datetime'];
    }
}
