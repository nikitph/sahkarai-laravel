<?php

namespace Tests\Feature;

use App\Actions\Billing\ProcessRazorpayWebhook;
use App\Actions\Chat\SendChatMessage;
use App\Actions\Ingestion\AcquireDocument;
use App\Actions\Notifications\NotifyRegulatoryUpdate;
use App\Ai\Agents\RegulatoryChatAgent;
use App\Contracts\Ingestion\SourceAdapter;
use App\Data\DocumentCandidate;
use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Enums\SubscriptionStatus;
use App\Enums\Tier;
use App\Jobs\Ingestion\RunSourcePoll;
use App\Models\Chat;
use App\Models\DocumentVersion;
use App\Models\RegulatoryDocument;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Archive\ArchiveSearch;
use App\Services\Ingestion\SourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SahkarAiProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_free_individual_account_without_an_organization(): void
    {
        Notification::fake();
        $this->post(route('register.store'), [
            'name' => 'Asha Member', 'email' => 'asha@example.test', 'locale' => 'gu',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'asha@example.test')->firstOrFail();
        $this->assertSame(Tier::Free, $user->tier);
        $this->assertSame('gu', $user->locale->value);
        $this->assertNull($user->current_organization_id);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'tier' => 'free', 'status' => 'free']);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'source_rbi_cadence' => 'daily_digest',
            'source_income_tax_cadence' => 'daily_digest',
            'source_gst_cadence' => 'daily_digest',
        ]);
        Notification::assertNothingSent();
    }

    public function test_free_users_see_the_original_but_not_the_interpretation(): void
    {
        [$document] = $this->documentWithInterpretation();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('archive.show', $document))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('capabilities.interpretations', false)
                ->where('document.latest_version.interpretation', null));
    }

    public function test_tier_one_users_receive_the_localized_interpretation(): void
    {
        [$document] = $this->documentWithInterpretation();
        $user = User::factory()->tier1()->create(['locale' => 'hi']);

        $this->actingAs($user)->get(route('archive.show', $document))
            ->assertInertia(fn ($page) => $page
                ->where('document.latest_version.interpretation_locale', 'hi')
                ->where('document.latest_version.interpretation.summary', 'Hindi summary'));
    }

    public function test_chat_is_private_and_a_message_debits_exactly_one_credit_idempotently(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $owner = User::factory()->tier2(3)->create();
        $other = User::factory()->tier2()->create();
        $chat = Chat::create([
            'user_id' => $owner->id, 'regulatory_document_id' => $document->id,
            'document_version_id' => $version->id, 'locale' => 'en',
        ]);

        $this->actingAs($other)->get(route('chats.show', $chat))->assertForbidden();
        RegulatoryChatAgent::fake(['A grounded answer.'])->preventStrayPrompts();
        $requestId = (string) Str::uuid();
        $message = app(SendChatMessage::class)->handle($owner, $chat, 'What changed?', $requestId);
        $same = app(SendChatMessage::class)->handle($owner->refresh(), $chat, 'What changed?', $requestId);

        $this->assertSame('assistant', $message->role);
        $this->assertSame($message->id, $same->id);
        $this->assertSame(2, $owner->refresh()->credits_balance);
        $this->assertDatabaseCount('credit_ledger', 1);
        $this->assertDatabaseCount('chat_messages', 2);
    }

    public function test_context_limit_closes_chat_without_debiting_or_persisting_message(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier2(5)->create();
        $chat = Chat::create([
            'user_id' => $user->id, 'regulatory_document_id' => $document->id, 'document_version_id' => $version->id,
            'context_tokens' => config('sahkarai.ai.context_window_tokens') - 1,
        ]);

        try {
            app(SendChatMessage::class)->handle($user, $chat, 'This cannot fit.', (string) Str::uuid());
            $this->fail('Expected context validation to fail.');
        } catch (ValidationException) {
            $this->assertSame('closed_context_full', $chat->refresh()->status);
            $this->assertSame(5, $user->refresh()->credits_balance);
            $this->assertDatabaseCount('chat_messages', 0);
        }
    }

    public function test_acquisition_is_idempotent_and_creates_a_linked_revision_for_changed_bytes(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['sahkarai.ingestion.storage_disk' => 'local']);
        $candidate = new DocumentCandidate(
            RegulatorySource::Rbi, 'RBI-1', 'A circular', 'https://example.test/circular.txt',
            documentType: DocumentType::Circular, applicability: Applicability::Ucb,
        );
        Http::fakeSequence()
            ->push('version one', 200, ['Content-Type' => 'text/plain'])
            ->push('version one', 200, ['Content-Type' => 'text/plain'])
            ->push('version two', 200, ['Content-Type' => 'text/plain']);
        $first = app(AcquireDocument::class)->handle($candidate);
        $duplicate = app(AcquireDocument::class)->handle($candidate);
        $second = app(AcquireDocument::class)->handle($candidate);

        $this->assertNotNull($first);
        $this->assertNull($duplicate);
        $this->assertSame(2, $second?->version);
        $this->assertSame($first?->id, $second?->supersedes_id);
        $this->assertDatabaseCount('regulatory_documents', 1);
        $this->assertDatabaseCount('document_versions', 2);
    }

    public function test_notification_dispatch_respects_subscription_start_and_is_idempotent(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier1()->create();
        $user->subscription()->create([
            'tier' => Tier::Tier1, 'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth(),
        ]);
        $user->notificationPreference()->create();

        app(NotifyRegulatoryUpdate::class)->handle($version);
        app(NotifyRegulatoryUpdate::class)->handle($version);

        $this->assertDatabaseCount('product_notifications', 1);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'channel' => 'in_app', 'status' => 'delivered']);
    }

    public function test_signed_and_idempotent_razorpay_webhook_activates_cycle_credits(): void
    {
        config(['sahkarai.razorpay.webhook_secret' => 'test-secret']);
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id, 'provider_subscription_id' => 'sub_123',
            'tier' => Tier::Tier2, 'status' => SubscriptionStatus::Pending,
        ]);
        $payload = [
            'event' => 'subscription.charged',
            'payload' => ['subscription' => ['entity' => [
                'id' => 'sub_123', 'status' => 'active', 'current_start' => now()->timestamp, 'current_end' => now()->addMonth()->timestamp,
            ]]],
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        foreach ([1, 2] as $attempt) {
            $this->call('POST', route('webhooks.razorpay'), server: [
                'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
                'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_123',
            ], content: $body)->assertOk();
        }

        $this->assertSame(Tier::Tier2, $user->refresh()->tier);
        $this->assertSame(200, $user->credits_balance);
        $this->assertDatabaseCount('processed_webhooks', 1);
        $this->assertDatabaseCount('credit_ledger', 1);
    }

    public function test_archive_supports_title_sort_and_interpretation_applicability_tags(): void
    {
        foreach ([['Zulu circular', ['generic']], ['Alpha circular', ['pacs']]] as [$title, $tags]) {
            $document = RegulatoryDocument::create([
                'source' => RegulatorySource::Rbi,
                'source_document_id' => (string) Str::uuid(),
                'title' => $title,
                'document_type' => DocumentType::Circular,
                'applicability' => Applicability::Generic,
                'applicability_tags' => $tags,
                'published_at' => now(),
            ]);
            $document->versions()->create([
                'version' => 1,
                'status' => 'published',
                'original_path' => 'originals/test.txt',
                'mime_type' => 'text/plain',
                'sha256' => hash('sha256', $title),
                'acquired_at' => now(),
            ]);
        }

        $result = app(ArchiveSearch::class)->search(['sort' => 'title', 'applicability' => 'pacs']);

        $this->assertSame(1, $result->total());
        $this->assertSame('Alpha circular', $result->items()[0]->title);
    }

    public function test_archive_filter_options_are_serialized_as_value_objects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('archive.index'))
            ->assertInertia(fn ($page) => $page
                ->where('filterOptions.sources.0.value', 'rbi')
                ->where('filterOptions.types.0.value', 'master_direction')
                ->where('filterOptions.applicability.0.value', 'pacs'));
    }

    public function test_interpretation_locale_can_be_switched_without_persisting_and_falls_back_to_english(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $interpretation = $version->interpretation;
        $payloads = $interpretation->locale_payloads;
        unset($payloads['hi']);
        $interpretation->update(['locale_payloads' => $payloads]);
        $user = User::factory()->tier1()->create(['locale' => 'hi']);

        $this->actingAs($user)->get(route('archive.show', ['document' => $document, 'locale' => 'hi']))
            ->assertInertia(fn ($page) => $page
                ->where('document.latest_version.requested_locale', 'hi')
                ->where('document.latest_version.interpretation_locale', 'en')
                ->where('document.latest_version.locale_fallback', true)
                ->where('document.latest_version.interpretation.summary', 'English summary'));

        $this->assertSame('hi', $user->refresh()->locale->value);
    }

    public function test_zero_credit_chat_exposes_reset_date_and_optional_topup_url(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier2(0)->create(['credits_reset_at' => '2026-08-01 00:00:00']);
        $chat = Chat::create([
            'user_id' => $user->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $version->id,
        ]);
        config(['sahkarai.credits.topup_url' => 'https://billing.example.test/topup']);

        $this->actingAs($user)->get(route('chats.show', $chat))
            ->assertInertia(fn ($page) => $page
                ->where('credits', 0)
                ->where('creditsResetAt', '2026-08-01')
                ->where('topupUrl', 'https://billing.example.test/topup'));
    }

    public function test_signed_topup_webhook_is_idempotent(): void
    {
        config(['sahkarai.razorpay.webhook_secret' => 'test-secret']);
        $user = User::factory()->tier2(0)->create();
        $payload = [
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_topup_1',
                'notes' => ['purpose' => 'credit_topup', 'user_id' => (string) $user->id, 'credits' => '100'],
            ]]],
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        foreach ([1, 2] as $attempt) {
            $this->call('POST', route('webhooks.razorpay'), server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
                'HTTP_X_RAZORPAY_EVENT_ID' => 'topup_evt_1',
            ], content: $body)->assertOk();
        }

        $this->assertSame(100, $user->refresh()->credits_balance);
        $this->assertDatabaseHas('credit_ledger', ['user_id' => $user->id, 'amount' => 100, 'reason' => 'topup']);
        $this->assertDatabaseCount('credit_ledger', 1);
    }

    public function test_tier_one_to_tier_two_charge_grants_prorated_credits(): void
    {
        Carbon::setTestNow('2026-05-16 00:00:00');
        $user = User::factory()->tier1()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'provider_subscription_id' => 'sub_upgrade',
            'tier' => Tier::Tier1,
            'pending_tier' => Tier::Tier2,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => '2026-05-01 00:00:00',
            'current_period_end' => '2026-05-31 00:00:00',
        ]);
        $payload = [
            'event' => 'subscription.charged',
            'payload' => ['subscription' => ['entity' => [
                'id' => 'sub_upgrade',
                'status' => 'active',
                'current_start' => now()->timestamp,
                'current_end' => now()->addMonth()->timestamp,
            ]]],
        ];

        app(ProcessRazorpayWebhook::class)->handle('upgrade_evt_1', $payload);

        $this->assertSame(Tier::Tier2, $user->refresh()->tier);
        $this->assertSame(100, $user->credits_balance);
        $this->assertSame(Tier::Tier2, $subscription->refresh()->tier);
        Carbon::setTestNow();
    }

    public function test_three_consecutive_source_failures_raise_one_ops_alert(): void
    {
        $adapter = Mockery::mock(SourceAdapter::class);
        $adapter->shouldReceive('discover')->times(3)->andThrow(new RuntimeException('feed unavailable'));
        $registry = Mockery::mock(SourceRegistry::class);
        $registry->shouldReceive('adapter')->times(3)->with(RegulatorySource::Rbi)->andReturn($adapter);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                (new RunSourcePoll(RegulatorySource::Rbi))->handle($registry, app(AcquireDocument::class));
            } catch (RuntimeException) {
                // The queue runtime will retry; the run itself remains auditable.
            }
        }

        $this->assertDatabaseCount('poll_runs', 3);
        $this->assertDatabaseCount('ops_alerts', 1);
        $this->assertDatabaseHas('ops_alerts', ['type' => 'source_poll_failed', 'severity' => 'critical']);
    }

    /** @return array{RegulatoryDocument, DocumentVersion} */
    private function documentWithInterpretation(): array
    {
        $document = RegulatoryDocument::create([
            'source' => RegulatorySource::Rbi, 'source_document_id' => (string) Str::uuid(),
            'title' => 'Regulatory test document', 'document_type' => DocumentType::Circular,
            'applicability' => Applicability::Generic, 'published_at' => now(),
        ]);
        $version = $document->versions()->create([
            'version' => 1, 'status' => 'published', 'original_path' => 'originals/test.txt',
            'mime_type' => 'text/plain', 'sha256' => hash('sha256', (string) Str::uuid()),
            'extracted_text' => 'The source document requires an implementation review.', 'acquired_at' => now(),
        ]);
        $version->interpretation()->create([
            'status' => 'published', 'locale_payloads' => [
                'en' => ['locale' => 'en', 'summary' => 'English summary', 'takeaways' => ['One', 'Two', 'Three'], 'glossary' => [], 'deadlines' => []],
                'hi' => ['locale' => 'hi', 'summary' => 'Hindi summary', 'takeaways' => ['One', 'Two', 'Three'], 'glossary' => [], 'deadlines' => []],
            ],
            'model_id' => 'test-model', 'prompt_version' => 'test.1', 'published_at' => now(),
        ]);

        return [$document, $version];
    }
}
