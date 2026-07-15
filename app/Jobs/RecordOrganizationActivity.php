<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RecordOrganizationActivity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $organizationId,
        public string $detail = 'queued',
    ) {}

    public function handle(TenantContext $context): void
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        $context->set($organization);

        try {
            DB::table('conformance_events')->insert([
                'organization_id' => $context->id(),
                'kind' => 'queue_job',
                'detail' => $this->detail,
                'created_at' => now(),
            ]);
        } finally {
            $context->clear();
        }
    }
}
