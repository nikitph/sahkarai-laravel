<?php

use App\Events\ConformancePing;
use App\Http\Controllers\Archive\ArchiveController;
use App\Http\Controllers\Archive\IssueReportController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Chat\ChatMessageController;
use App\Http\Controllers\Chat\ChatStreamController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Exports\ChatExportController;
use App\Http\Controllers\Exports\InterpretationExportController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Ops\CreditAdjustmentController;
use App\Http\Controllers\Ops\IssueTriageController;
use App\Http\Controllers\Ops\OpsDashboardController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use App\Jobs\AlwaysFails;
use App\Jobs\RecordOrganizationActivity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['en', 'hi', 'gu', 'mr'], true), 404);
    session(['locale' => $locale]);

    return back();
})->name('locale.update');
Route::post('webhooks/razorpay', RazorpayWebhookController::class)->name('webhooks.razorpay');

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

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('archive', [ArchiveController::class, 'index'])->name('archive.index');
    Route::get('archive/{document}', [ArchiveController::class, 'show'])->name('archive.show');
    Route::get('archive/{document}/download', [ArchiveController::class, 'download'])->name('archive.download');
    Route::post('interpretations/{interpretation}/issues', [IssueReportController::class, 'store'])->name('interpretations.issues.store');
    Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
    Route::post('documents/{document}/chats', [ChatController::class, 'store'])->name('chats.store');
    Route::get('chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
    Route::post('chats/{chat}/messages', [ChatMessageController::class, 'store'])->name('chats.messages.store');
    Route::post('chats/{chat}/stream', ChatStreamController::class)->name('chats.stream');
    Route::patch('chats/{chat}/close', [ChatController::class, 'close'])->name('chats.close');
    Route::post('chats/{chat}/restart', [ChatController::class, 'restart'])->name('chats.restart');
    Route::get('chats/{chat}/export/{format}', ChatExportController::class)->name('chats.export');
    Route::get('interpretations/{interpretation}/export/{format}', InterpretationExportController::class)->name('interpretations.export');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('billing/resume', [BillingController::class, 'resume'])->name('billing.resume');

    Route::middleware('admin')->prefix('ops')->name('ops.')->group(function () {
        Route::get('/', OpsDashboardController::class)->name('dashboard');
        Route::patch('issues/{issue}', [IssueTriageController::class, 'update'])->name('issues.update');
        Route::post('users/{user}/credits', [CreditAdjustmentController::class, 'store'])->name('users.credits.store');
    });

    Route::post('conformance/queue', function () {
        abort_unless((bool) auth()->user()->current_organization_id, 422);
        RecordOrganizationActivity::dispatch((int) auth()->user()->current_organization_id, 'from-http');

        return ['dispatched' => true];
    })->name('conformance.queue');
    Route::post('conformance/queue/fail', function () {
        AlwaysFails::dispatch();

        return ['dispatched' => true];
    })->name('conformance.queue.fail');
});

require __DIR__.'/settings.php';
