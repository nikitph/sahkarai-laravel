<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'actor_id', 'event', 'subject_type', 'subject_id', 'metadata', 'ip_address'])]
class AuditEvent extends Model
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
