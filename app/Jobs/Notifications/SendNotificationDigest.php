<?php

namespace App\Jobs\Notifications;

use App\Models\NotificationDelivery;
use App\Models\RegulatoryDocument;
use App\Models\User;
use App\Notifications\RegulatoryUpdateMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationDigest implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $cadence) {}

    public function handle(): void
    {
        User::query()
            ->whereIn('tier', ['tier_1', 'tier_2'])
            ->with('notificationPreference')
            ->each(function (User $user): void {
                if (! $user->notificationPreference?->email_enabled) {
                    return;
                }
                $since = $this->cadence === 'weekly_digest' ? now()->subWeek() : now()->subDay();
                $notifications = $user->productNotifications()
                    ->where('created_at', '>=', $since)
                    ->whereDoesntHave('deliveries', fn ($query) => $query->where('channel', 'email'))
                    ->get();
                foreach ($notifications as $notification) {
                    $document = RegulatoryDocument::query()->whereKey($notification->data['document_id'] ?? null)->first();
                    if (! $document) {
                        continue;
                    }
                    $sourceField = match ($document->source->value) {
                        'rbi' => 'source_rbi',
                        'income_tax' => 'source_income_tax',
                        'gst' => 'source_gst',
                    };
                    if (! $user->notificationPreference->{$sourceField}
                        || $user->notificationPreference->{$sourceField.'_cadence'}->value !== $this->cadence) {
                        continue;
                    }
                    $user->notify((new RegulatoryUpdateMail($document))->locale($user->locale->value));
                    NotificationDelivery::create([
                        'product_notification_id' => $notification->getKey(), 'user_id' => $user->getKey(),
                        'channel' => 'email', 'status' => 'queued', 'locale' => $user->locale,
                    ]);
                }
            });
    }
}
