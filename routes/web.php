<?php

use App\Events\ConformancePing;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Jobs\AlwaysFails;
use App\Jobs\RecordOrganizationActivity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

Route::get('conformance/sse', function () {
    return response()->stream(function (): void {
        for ($tick = 1; $tick <= 5; $tick++) {
            if (connection_aborted()) {
                Log::info('sse.client_disconnected', ['at_tick' => $tick]);

                return;
            }

            echo "event: tick\n";
            echo 'data: '.json_encode(['n' => $tick, 'ts' => now()->toIso8601String()])."\n\n";

            if (ob_get_level() > 0) {
                @ob_flush();
            }

            flush();
            usleep(250_000);
        }

        echo "event: done\n";
        echo "data: {\"ok\":true}\n\n";
        flush();
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
})->name('conformance.sse');

Route::get('conformance/broadcast', function () {
    event(new ConformancePing('hello-from-sahkarai'));

    return ['broadcast' => 'sent'];
})->name('conformance.broadcast');

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');
    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::post('members/invitations', [MemberController::class, 'store'])->name('members.store');
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('conformance/queue', function () {
        RecordOrganizationActivity::dispatch((int) auth()->user()->current_organization_id, 'from-http');

        return ['dispatched' => true];
    })->name('conformance.queue');
    Route::post('conformance/queue/fail', function () {
        AlwaysFails::dispatch();

        return ['dispatched' => true];
    })->name('conformance.queue.fail');
});

require __DIR__.'/settings.php';
