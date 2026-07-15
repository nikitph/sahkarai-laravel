<?php

use App\Jobs\RecordOrganizationActivity;
use App\Models\Organization;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('conformance:dispatch-scheduled', function (): void {
    Organization::query()->each(
        fn (Organization $organization) => RecordOrganizationActivity::dispatch(
            (int) $organization->getKey(),
            'scheduled',
        ),
    );

    $this->info('Scheduled organization work dispatched.');
})->purpose('Dispatch the tenant-aware scheduled-work conformance jobs');

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('conformance:dispatch-scheduled')->hourly();
