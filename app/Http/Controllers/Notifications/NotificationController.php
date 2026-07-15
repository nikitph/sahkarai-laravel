<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\ProductNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProductNotification::class);

        return Inertia::render('notifications/index', [
            'notifications' => $request->user()->productNotifications()->latest()->paginate(20),
            'preferences' => $request->user()->notificationPreference,
        ]);
    }

    public function read(Request $request, ProductNotification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);
        $notification->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ProductNotification::class);
        $request->user()->productNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function preferences(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ProductNotification::class);
        $validated = $request->validate([
            'source_rbi' => ['required', 'boolean'],
            'source_income_tax' => ['required', 'boolean'],
            'source_gst' => ['required', 'boolean'],
            'source_rbi_cadence' => ['required', 'in:immediate,daily_digest,weekly_digest'],
            'source_income_tax_cadence' => ['required', 'in:immediate,daily_digest,weekly_digest'],
            'source_gst_cadence' => ['required', 'in:immediate,daily_digest,weekly_digest'],
        ]);
        $request->user()->notificationPreference()->updateOrCreate([], $validated);

        return back()->with('success', 'Notification preferences updated.');
    }
}
