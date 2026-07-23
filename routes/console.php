<?php

use App\Enums\RegulatorySource;
use App\Jobs\Account\PurgeExpiredAccounts;
use App\Jobs\Billing\ApplyPendingSubscriptionChanges;
use App\Jobs\Billing\ReconcileSubscriptions;
use App\Jobs\Ingestion\RunSourcePoll;
use App\Jobs\Notifications\SendNotificationDigest;
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

Artisan::command('regulatory:backfill {--sync}', function (): void {
    foreach (RegulatorySource::pollableCases() as $source) {
        if ($this->option('sync')) {
            RunSourcePoll::dispatchSync($source, 'backfill');
        } else {
            RunSourcePoll::dispatch($source, 'backfill');
        }
    }
    $this->info('One-year regulatory backfill dispatched for RBI, Income Tax, and GST.');
})->purpose('Run the one-off historical regulatory backfill');

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('conformance:dispatch-scheduled')->hourly();
Schedule::job(new RunSourcePoll(RegulatorySource::Rbi))->dailyAt('00:05');
Schedule::job(new RunSourcePoll(RegulatorySource::IncomeTax))->dailyAt('00:20');
Schedule::job(new RunSourcePoll(RegulatorySource::Gst))->dailyAt('00:35');
Schedule::job(new RunSourcePoll(RegulatorySource::Rbi))->dailyAt('12:05');
Schedule::job(new RunSourcePoll(RegulatorySource::IncomeTax))->dailyAt('12:20');
Schedule::job(new RunSourcePoll(RegulatorySource::Gst))->dailyAt('12:35');
Schedule::job(new SendNotificationDigest('daily_digest'))->dailyAt('03:00');
Schedule::job(new SendNotificationDigest('weekly_digest'))->mondays()->at('03:30');
Schedule::job(new ReconcileSubscriptions)->dailyAt('02:00');
Schedule::job(new ApplyPendingSubscriptionChanges)->everyFiveMinutes();
Schedule::job(new PurgeExpiredAccounts)->dailyAt('02:30');
