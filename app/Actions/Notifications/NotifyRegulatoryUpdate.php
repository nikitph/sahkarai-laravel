<?php

namespace App\Actions\Notifications;

use App\Models\DocumentVersion;
use App\Models\NotificationDelivery;
use App\Models\ProductNotification;
use App\Models\User;
use App\Notifications\RegulatoryUpdateMail;

class NotifyRegulatoryUpdate
{
    public function handle(DocumentVersion $version): void
    {
        $document = $version->document;
        if ($document->is_backfill || ! in_array($version->interpretation_status, ['published', 'partial'], true)) {
            return;
        }

        User::query()
            ->whereIn('tier', ['tier_1', 'tier_2'])
            ->with(['subscription', 'notificationPreference'])
            ->each(function (User $user) use ($document, $version): void {
                $preferences = $user->notificationPreference;
                $sourceField = match ($document->source->value) {
                    'rbi' => 'source_rbi',
                    'income_tax' => 'source_income_tax',
                    'gst' => 'source_gst',
                };
                if (! $preferences?->{$sourceField}) {
                    return;
                }
                $cadence = $preferences->{$sourceField.'_cadence'};

                $eligible = $version->version > 1
                    ? $user->documentViews()->where('document_version_id', $version->supersedes_id)->exists()
                    : $user->subscription?->current_period_start?->lte($version->acquired_at) ?? false;
                if (! $eligible) {
                    return;
                }

                $dedupe = "regulatory:{$version->getKey()}:user:{$user->getKey()}";
                $priorVersion = $version->supersedes;
                $notification = ProductNotification::query()->firstOrCreate(
                    ['dedupe_key' => $dedupe],
                    [
                        'user_id' => $user->getKey(),
                        'type' => $version->version > 1 ? 'regulatory_revision' : 'regulatory_update',
                        'title' => $document->title,
                        'body' => $priorVersion
                            ? __('notifications.regulatory_revision_body', [
                                'title' => $document->title,
                                'version' => $version->version,
                                'prior_version' => $priorVersion->version,
                            ], $user->locale->value)
                            : __('notifications.regulatory_update_body', ['title' => $document->title], $user->locale->value),
                        'data' => [
                            'document_id' => $document->getKey(),
                            'version_id' => $version->getKey(),
                            'supersedes_version_id' => $priorVersion?->getKey(),
                            'supersedes_version' => $priorVersion?->version,
                        ],
                    ],
                );

                NotificationDelivery::query()->firstOrCreate([
                    'product_notification_id' => $notification->getKey(),
                    'user_id' => $user->getKey(),
                    'channel' => 'in_app',
                ], ['status' => 'delivered', 'locale' => $user->locale, 'delivered_at' => now()]);

                if ($cadence->value === 'immediate') {
                    $user->notify((new RegulatoryUpdateMail($document))->locale($user->locale->value));
                    NotificationDelivery::query()->firstOrCreate([
                        'product_notification_id' => $notification->getKey(),
                        'user_id' => $user->getKey(),
                        'channel' => 'email',
                    ], ['status' => 'queued', 'locale' => $user->locale]);
                }
            });
    }
}
