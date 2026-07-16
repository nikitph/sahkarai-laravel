<?php

namespace App\Actions\Billing;

use App\Actions\Credits\AdjustCredits;
use App\Enums\CreditReason;
use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Models\NotificationDelivery;
use App\Models\OpsAlert;
use App\Models\ProcessedWebhook;
use App\Models\ProductNotification;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Billing\BillingStatusMail;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessRazorpayWebhook
{
    public function __construct(private readonly AdjustCredits $credits) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload): ProcessedWebhook
    {
        $eventType = (string) ($payload['event'] ?? 'unknown');
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $webhook = ProcessedWebhook::query()->firstOrCreate(
            ['provider' => 'razorpay', 'provider_event_id' => $eventId],
            ['event_type' => $eventType, 'payload_hash' => $payloadHash, 'payload' => $payload],
        );
        if (! hash_equals($webhook->payload_hash, $payloadHash)) {
            OpsAlert::query()->firstOrCreate([
                'type' => 'webhook_replay_mismatch',
                'title' => "Razorpay event payload changed: {$eventId}",
                'resolved_at' => null,
            ], [
                'severity' => 'critical',
                'details' => 'A duplicate provider event ID arrived with a different payload hash.',
                'context' => ['event_id' => $eventId, 'event_type' => $eventType],
            ]);
            throw new RuntimeException("Razorpay event {$eventId} was replayed with a different payload.");
        }
        if ($webhook->status === 'processed') {
            return $webhook;
        }

        try {
            DB::transaction(function () use ($payload, $eventType, $eventId, $webhook): void {
                $lockedWebhook = ProcessedWebhook::query()->whereKey($webhook->getKey())->lockForUpdate()->firstOrFail();
                if ($lockedWebhook->status === 'processed') {
                    return;
                }

                if ($this->isCreditTopup($payload, $eventType)) {
                    $this->applyCreditTopup($payload, $eventId);
                } else {
                    $entity = Arr::get($payload, 'payload.subscription.entity', []);
                    $providerId = $entity['id'] ?? Arr::get($payload, 'payload.payment.entity.subscription_id');
                    $subscription = Subscription::query()->where('provider_subscription_id', $providerId)->lockForUpdate()->first();
                    if (! $subscription) {
                        throw new RuntimeException("Unknown Razorpay subscription: {$providerId}");
                    }

                    $user = $subscription->user()->lockForUpdate()->firstOrFail();
                    $targetTier = $subscription->pending_tier ?? $subscription->tier;
                    $previousPeriodStart = $subscription->current_period_start;
                    $previousPeriodEnd = $subscription->current_period_end;
                    $status = match ($eventType) {
                        'subscription.activated', 'subscription.charged' => SubscriptionStatus::Active,
                        'subscription.halted' => SubscriptionStatus::Halted,
                        'subscription.cancelled' => SubscriptionStatus::Cancelled,
                        'subscription.completed' => SubscriptionStatus::Completed,
                        default => null,
                    };
                    if ($status) {
                        $subscription->update([
                            'status' => $status,
                            'current_period_start' => isset($entity['current_start']) ? now()->setTimestamp($entity['current_start']) : $subscription->current_period_start,
                            'current_period_end' => isset($entity['current_end']) ? now()->setTimestamp($entity['current_end']) : $subscription->current_period_end,
                            'cancelled_at' => $status === SubscriptionStatus::Cancelled ? now() : $subscription->cancelled_at,
                            'provider_payload' => [...($subscription->provider_payload ?? []), ...$entity],
                        ]);
                    }

                    if (in_array($eventType, ['subscription.activated', 'subscription.charged'], true)) {
                        $subscription->update(['tier' => $targetTier, 'pending_tier' => null]);
                        $previousTier = $user->tier;
                        $user->update(['tier' => $targetTier]);
                        if ($targetTier->canChat() && $eventType === 'subscription.charged') {
                            $grant = $this->cycleCreditGrant($targetTier, $previousTier, $previousPeriodStart, $previousPeriodEnd);
                            if ($user->credits_balance > 0) {
                                $this->credits->handle($user, -$user->credits_balance, CreditReason::Adjustment, "razorpay-cycle-expiry:{$eventId}", $subscription, [
                                    'kind' => 'unused_cycle_expiry',
                                ]);
                            }
                            $this->credits->handle($user, $grant, CreditReason::GrantCycle, "razorpay-cycle:{$eventId}", $subscription);
                            $user->update(['credits_reset_at' => $subscription->current_period_end]);
                        }
                    }

                    if (in_array($eventType, ['payment.failed', 'subscription.pending', 'subscription.halted'], true)) {
                        $notification = ProductNotification::query()->firstOrCreate(['dedupe_key' => "billing-failed:{$eventId}"], [
                            'user_id' => $user->getKey(), 'type' => 'billing_failed', 'title' => 'Payment needs attention',
                            'body' => 'Your renewal payment failed. Update billing to keep paid features active.', 'data' => [],
                        ]);
                        NotificationDelivery::query()->firstOrCreate([
                            'product_notification_id' => $notification->getKey(), 'user_id' => $user->getKey(), 'channel' => 'in_app',
                        ], ['status' => 'delivered', 'locale' => $user->locale, 'delivered_at' => now()]);
                        $user->notify((new BillingStatusMail($notification->title, $notification->body))->locale($user->locale->value));
                        NotificationDelivery::query()->firstOrCreate([
                            'product_notification_id' => $notification->getKey(), 'user_id' => $user->getKey(), 'channel' => 'email',
                        ], ['status' => 'queued', 'locale' => $user->locale]);
                    }

                    if (in_array($eventType, ['subscription.cancelled', 'subscription.completed'], true)) {
                        $user->update(['tier' => Tier::Free, 'credits_balance' => 0]);
                        $subscription->update(['tier' => Tier::Free, 'pending_tier' => null]);
                    }

                    if ($eventType === 'payment.refunded') {
                        $delta = (int) Arr::get($payload, 'payload.refund.entity.notes.credits_delta', 0);
                        if ($delta !== 0) {
                            $this->credits->handle($user, $delta, CreditReason::Adjustment, "razorpay-refund:{$eventId}", $subscription);
                        }
                    }
                }

                $lockedWebhook->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
            }, attempts: 3);
        } catch (Throwable $exception) {
            $webhook->update(['status' => 'failed', 'error' => $exception->getMessage()]);
            OpsAlert::create(['type' => 'webhook_failed', 'severity' => 'critical', 'title' => "Razorpay webhook failed: {$eventType}", 'details' => $exception->getMessage(), 'context' => ['event_id' => $eventId]]);
            throw $exception;
        }

        return $webhook->refresh();
    }

    /** @param array<string, mixed> $payload */
    private function isCreditTopup(array $payload, string $eventType): bool
    {
        return $eventType === 'payment.captured'
            && Arr::get($payload, 'payload.payment.entity.notes.purpose') === 'credit_topup';
    }

    /** @param array<string, mixed> $payload */
    private function applyCreditTopup(array $payload, string $eventId): void
    {
        $userId = (int) Arr::get($payload, 'payload.payment.entity.notes.user_id', 0);
        $amount = (int) Arr::get($payload, 'payload.payment.entity.notes.credits', 0);
        if ($userId < 1 || $amount < 1 || $amount > 10000) {
            throw new RuntimeException('The credit top-up payload is invalid.');
        }

        $user = User::query()->whereKey($userId)->whereIn('tier', [Tier::Tier2, Tier::Tier3])->firstOrFail();
        $this->credits->handle($user, $amount, CreditReason::TopUp, "razorpay-topup:{$eventId}", metadata: [
            'provider_payment_id' => Arr::get($payload, 'payload.payment.entity.id'),
        ]);
    }

    private function cycleCreditGrant(Tier $targetTier, Tier $previousTier, ?CarbonInterface $periodStart, ?CarbonInterface $periodEnd): int
    {
        $fullGrant = (int) config("sahkarai.tiers.{$targetTier->value}.monthly_credits");
        if ($previousTier->canChat() || $previousTier === Tier::Free || ! $periodStart || ! $periodEnd) {
            return $fullGrant;
        }

        $totalSeconds = max(1, $periodEnd->getTimestamp() - $periodStart->getTimestamp());
        $remainingSeconds = max(0, $periodEnd->getTimestamp() - now()->getTimestamp());

        return max(1, (int) floor($fullGrant * min(1, $remainingSeconds / $totalSeconds)));
    }
}
