<?php

namespace App\Support\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    /** @param array<string, mixed> $metadata */
    public function record(string $event, ?Model $subject = null, array $metadata = []): AuditEvent
    {
        return $this->recordAs(auth()->user(), $event, $subject, $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public function recordAs(?User $actor, string $event, ?Model $subject = null, array $metadata = []): AuditEvent
    {
        $context = app(TenantContext::class);

        return AuditEvent::create([
            'organization_id' => $context->check() ? $context->id() : null,
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
