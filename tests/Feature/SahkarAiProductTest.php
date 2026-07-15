<?php

namespace Tests\Feature;

use App\Actions\Billing\ProcessRazorpayWebhook;
use App\Actions\Chat\SendChatMessage;
use App\Actions\Ingestion\AcquireDocument;
use App\Actions\Interpretations\GenerateLocaleInterpretation;
use App\Actions\Notifications\NotifyRegulatoryUpdate;
use App\Ai\Agents\RegulatoryChatAgent;
use App\Ai\Agents\RegulatoryInterpretationAgent;
use App\Contracts\Billing\BillingGateway;
use App\Contracts\Ingestion\SourceAdapter;
use App\Data\DocumentCandidate;
use App\Enums\Applicability;
use App\Enums\DocumentType;
use App\Enums\RegulatorySource;
use App\Enums\SubscriptionStatus;
use App\Enums\SupportedLocale;
use App\Enums\Tier;
use App\Jobs\Account\PurgeExpiredAccounts;
use App\Jobs\Billing\ApplyPendingSubscriptionChanges;
use App\Jobs\Billing\ReconcileSubscriptions;
use App\Jobs\Ingestion\ExtractDocumentText;
use App\Jobs\Ingestion\RunSourcePoll;
use App\Jobs\Interpretations\GenerateInterpretation;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\CreditLedger;
use App\Models\DocumentVersion;
use App\Models\DocumentView;
use App\Models\IssueReport;
use App\Models\PollRun;
use App\Models\ProductNotification;
use App\Models\ReconciliationDrift;
use App\Models\RegulatoryDocument;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\RegulatoryUpdateMail;
use App\Services\Archive\ArchiveSearch;
use App\Services\Ingestion\ConfiguredFeedAdapter;
use App\Services\Ingestion\SourceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
        $this->assertStringContainsString('/RBI-1.txt', $first?->original_path ?? '');
        $this->assertStringContainsString('/RBI-1-v2.txt', $second?->original_path ?? '');
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

    public function test_notification_preferences_expose_only_sources_and_email_cadence(): void
    {
        Notification::fake();
        [$document, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier1()->create();
        $user->subscription()->create([
            'tier' => Tier::Tier1,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $version->acquired_at->copy()->subDay(),
            'current_period_end' => now()->addMonth(),
        ]);
        $user->notificationPreference()->create(['source_rbi_cadence' => 'immediate']);

        $this->assertFalse(Schema::hasColumn('notification_preferences', 'in_app_enabled'));
        $this->assertFalse(Schema::hasColumn('notification_preferences', 'email_enabled'));
        $this->actingAs($user)->get(route('notifications.index'))
            ->assertInertia(fn ($page) => $page
                ->missing('preferences.in_app_enabled')
                ->missing('preferences.email_enabled'));

        app(NotifyRegulatoryUpdate::class)->handle($version);

        Notification::assertSentTo($user, RegulatoryUpdateMail::class);
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $user->id,
            'channel' => 'in_app',
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $user->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);
    }

    public function test_invalid_notification_cadence_is_rejected_without_changing_preferences(): void
    {
        $user = User::factory()->tier1()->create();
        $preference = $user->notificationPreference()->create();

        $this->actingAs($user)->patch(route('notifications.preferences'), [
            'source_rbi' => true,
            'source_income_tax' => true,
            'source_gst' => true,
            'source_rbi_cadence' => 'hourly',
            'source_income_tax_cadence' => 'daily_digest',
            'source_gst_cadence' => 'weekly_digest',
        ])->assertSessionHasErrors('source_rbi_cadence');

        $this->assertSame('daily_digest', $preference->refresh()->source_rbi_cadence->value);
    }

    public function test_new_document_notification_eligibility_uses_version_ingestion_time(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $document->update(['created_at' => now()]);
        $version->update(['acquired_at' => now()->subMonths(2)]);
        $user = User::factory()->tier1()->create();
        $user->subscription()->create([
            'tier' => Tier::Tier1,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);
        $user->notificationPreference()->create();

        app(NotifyRegulatoryUpdate::class)->handle($version->fresh());

        $this->assertDatabaseMissing('product_notifications', [
            'user_id' => $user->id,
            'dedupe_key' => "regulatory:{$version->id}:user:{$user->id}",
        ]);
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

    public function test_new_paid_subscription_launches_checkout_without_granting_access(): void
    {
        config(['sahkarai.razorpay.key_id' => 'rzp_test_key']);
        $user = User::factory()->create();
        $gateway = Mockery::mock(BillingGateway::class);
        $gateway->shouldReceive('createSubscription')
            ->once()
            ->withArgs(fn (User $candidate, Tier $tier) => $candidate->is($user) && $tier === Tier::Tier1)
            ->andReturn(['id' => 'sub_checkout_1', 'status' => 'created']);
        $this->app->instance(BillingGateway::class, $gateway);

        $this->actingAs($user)->post(route('billing.subscribe'), ['tier' => 'tier_1'])
            ->assertRedirect()
            ->assertSessionHas('razorpay_checkout', [
                'key' => 'rzp_test_key',
                'subscription_id' => 'sub_checkout_1',
                'tier' => 'tier_1',
                'name' => $user->name,
                'email' => $user->email,
            ]);

        $this->assertSame(Tier::Free, $user->refresh()->tier);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'provider_subscription_id' => 'sub_checkout_1',
            'tier' => 'tier_1',
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('credit_ledger', ['user_id' => $user->id]);
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
                'extraction_status' => 'ok',
                'interpretation_status' => 'published',
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

    public function test_archive_search_stub_uses_the_matched_field_and_all_applicability_tags(): void
    {
        [$document] = $this->documentWithInterpretation();
        $document->update([
            'title' => 'Capital Adequacy Update',
            'applicability_tags' => ['ucb', 'dccb'],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('archive.index', ['q' => 'capital']))
            ->assertInertia(fn ($page) => $page
                ->where('documents.data.0.title', 'Capital Adequacy Update')
                ->where('documents.data.0.snippet', 'Capital Adequacy Update')
                ->where('documents.data.0.matched_field', 'title')
                ->where('documents.data.0.applicability_tags', ['ucb', 'dccb']));
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

    public function test_structured_interpretation_response_is_validated_and_updates_document_metadata(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $payload = [
            'locale' => 'en',
            'summary' => implode(' ', array_fill(0, 150, 'word')),
            'takeaways' => ['Review the circular.', 'Assign an owner.', 'Track the deadline.'],
            'glossary' => [['term' => 'UCB', 'definition' => 'Urban cooperative bank.']],
            'deadlines' => [['due_date' => '2026-08-31', 'description' => 'Complete implementation.']],
            'applicability_tags' => ['ucb', 'generic'],
            'effective_date' => '2026-08-01',
            'document_type' => 'notification',
        ];
        RegulatoryInterpretationAgent::fake([$payload])->preventStrayPrompts();

        $result = app(GenerateLocaleInterpretation::class)->handle($version, SupportedLocale::English);

        $this->assertEquals($payload, $result);
        $this->assertSame(['ucb', 'generic'], $document->refresh()->applicability_tags);
        $this->assertSame(DocumentType::Notification, $document->document_type);
        $this->assertSame('2026-08-01', $document->effective_at?->toDateString());
    }

    public function test_interpretation_metadata_is_shared_and_cannot_diverge_by_locale(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $version->interpretation()->delete();
        $summary = implode(' ', array_fill(0, 150, 'word'));
        $responses = collect(SupportedLocale::cases())->map(function (SupportedLocale $locale) use ($summary): array {
            return [
                'locale' => $locale->value,
                'summary' => $summary,
                'takeaways' => ['First action.', 'Second action.', 'Third action.'],
                'glossary' => [],
                'deadlines' => [[
                    'due_date' => $locale === SupportedLocale::English ? '2026-09-30' : '2099-01-01',
                    'description' => 'Shared deadline.',
                ]],
                'applicability_tags' => $locale === SupportedLocale::English ? ['ucb'] : ['pacs'],
                'effective_date' => $locale === SupportedLocale::English ? '2026-08-01' : '2099-01-01',
                'document_type' => $locale === SupportedLocale::English ? 'circular' : 'faq',
            ];
        })->all();
        RegulatoryInterpretationAgent::fake($responses)->preventStrayPrompts();

        (new GenerateInterpretation($version->id))->handle(
            app(GenerateLocaleInterpretation::class),
            app(NotifyRegulatoryUpdate::class),
        );

        $interpretation = $version->interpretation()->firstOrFail();
        $this->assertArrayNotHasKey('applicability_tags', $interpretation->locale_payloads['hi']);
        $this->assertSame(['ucb'], $interpretation->applicability_tags);
        $this->assertSame(['ucb'], $interpretation->payloadFor('hi')['applicability_tags']);
        $this->assertSame('2026-08-01', $interpretation->payloadFor('gu')['effective_date']);
        $this->assertSame('circular', $interpretation->payloadFor('mr')['document_type']);
        $this->assertSame('2026-09-30', $interpretation->payloadFor('hi')['deadlines'][0]['due_date']);
        $this->assertSame(['ucb'], $document->refresh()->applicability_tags);
    }

    public function test_text_extraction_is_idempotent_after_success(): void
    {
        Storage::fake('local');
        config(['sahkarai.ingestion.storage_disk' => 'local']);
        [, $version] = $this->documentWithInterpretation();
        $version->update(['extracted_at' => now()]);
        Queue::fake();

        (new ExtractDocumentText($version->id))->handle();

        Queue::assertNotPushed(GenerateInterpretation::class);
        $version->refresh();
        $this->assertSame('The source document requires an implementation review.', $version->extracted_text);
        $this->assertSame('extracted/test.txt', $version->extracted_path);
        Storage::disk('local')->assertExists('extracted/test.txt');
        $this->assertSame($version->extracted_text, Storage::disk('local')->get($version->extracted_path));
    }

    public function test_successful_extraction_writes_a_canonical_text_artifact(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['sahkarai.ingestion.storage_disk' => 'local']);
        [$document] = $this->documentWithInterpretation();
        $version = $document->versions()->create([
            'version' => 2,
            'status' => 'acquired',
            'original_path' => 'originals/rbi/2026/05/RBI-CIRC-2026-001.html',
            'mime_type' => 'text/html',
            'sha256' => hash('sha256', (string) Str::uuid()),
            'acquired_at' => now(),
        ]);
        Storage::disk('local')->put($version->original_path, '<h1>Capital adequacy</h1><p>Review required.</p>');

        (new ExtractDocumentText($version->id))->handle();

        $version->refresh();
        $this->assertSame('extracted', $version->status);
        $this->assertSame('extracted/rbi/2026/05/RBI-CIRC-2026-001.txt', $version->extracted_path);
        Storage::disk('local')->assertExists($version->extracted_path);
        $this->assertStringContainsString('Capital adequacy', Storage::disk('local')->get($version->extracted_path));
        Queue::assertPushed(GenerateInterpretation::class, fn (GenerateInterpretation $job) => $job->documentVersionId === $version->id);
    }

    public function test_extraction_failure_records_its_own_terminal_status_without_starting_interpretation(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['sahkarai.ingestion.storage_disk' => 'local']);
        [, $version] = $this->documentWithInterpretation();
        $version->interpretation()->delete();
        $version->update([
            'status' => 'acquired',
            'extraction_status' => 'pending',
            'interpretation_status' => 'pending',
            'mime_type' => 'image/png',
        ]);
        Storage::disk('local')->put($version->original_path, 'not extractable');

        try {
            (new ExtractDocumentText($version->id))->handle();
            $this->fail('The extraction job should fail for an unsupported original.');
        } catch (RuntimeException) {
            $version->refresh();
            $this->assertSame('extraction_failed', $version->status);
            $this->assertSame('failed', $version->extraction_status);
            $this->assertSame('pending', $version->interpretation_status);
            Queue::assertNotPushed(GenerateInterpretation::class);
        }
    }

    public function test_interpretation_generation_records_a_separate_published_status_for_all_locales(): void
    {
        [, $version] = $this->documentWithInterpretation();
        $version->interpretation()->delete();
        $version->update([
            'status' => 'extracted',
            'extraction_status' => 'ok',
            'interpretation_status' => 'pending',
        ]);
        $responses = collect(SupportedLocale::cases())->map(function (SupportedLocale $locale): array {
            return [
                'locale' => $locale->value,
                'summary' => implode(' ', array_fill(0, 150, "{$locale->value}-word")),
                'takeaways' => ['Review the publication.', 'Assign an owner.', 'Track implementation.'],
                'glossary' => [],
                'deadlines' => [],
                'applicability_tags' => ['generic'],
                'effective_date' => null,
                'document_type' => 'circular',
            ];
        })->all();
        RegulatoryInterpretationAgent::fake($responses)->preventStrayPrompts();

        (new GenerateInterpretation($version->id))->handle(
            app(GenerateLocaleInterpretation::class),
            app(NotifyRegulatoryUpdate::class),
        );

        $version->refresh();
        $this->assertSame('ok', $version->extraction_status);
        $this->assertSame('published', $version->interpretation_status);
        $this->assertSame('published', $version->status);
        $this->assertEqualsCanonicalizing(
            collect(SupportedLocale::cases())->pluck('value')->all(),
            array_keys($version->interpretation->locale_payloads),
        );
    }

    public function test_interpretation_generation_marks_terminal_failure_after_three_locale_attempts(): void
    {
        Log::spy();
        [, $version] = $this->documentWithInterpretation();
        $version->interpretation()->delete();
        $version->update([
            'status' => 'extracted',
            'extraction_status' => 'ok',
            'interpretation_status' => 'pending',
        ]);
        RegulatoryInterpretationAgent::fake(
            fn () => throw new RuntimeException('provider unavailable'),
        )->preventStrayPrompts();
        $job = new GenerateInterpretation($version->id);

        foreach ([1, 2, 3] as $attempt) {
            try {
                $job->handle(app(GenerateLocaleInterpretation::class), app(NotifyRegulatoryUpdate::class));
                $this->assertSame(3, $attempt);
            } catch (RuntimeException $exception) {
                $this->assertLessThan(3, $attempt);
                $this->assertSame('One or more locales require another generation attempt.', $exception->getMessage());
            }
        }

        $version->refresh();
        $this->assertSame('ok', $version->extraction_status);
        $this->assertSame('failed', $version->interpretation_status);
        $this->assertSame('interpretation_failed', $version->status);
        $this->assertSame('failed', $version->interpretation->status);
        $this->assertSame(
            ['en' => 3, 'hi' => 3, 'gu' => 3, 'mr' => 3],
            $version->interpretation->locale_attempts,
        );
    }

    public function test_partial_poll_records_the_source_document_that_failed_acquisition(): void
    {
        Log::spy();
        $candidate = new DocumentCandidate(
            RegulatorySource::Rbi,
            'RBI-BROKEN-1',
            'Unavailable circular',
            'https://example.test/unavailable.pdf',
            'https://example.test/unavailable.pdf',
            publishedAt: CarbonImmutable::parse('2026-05-15'),
        );
        $adapter = Mockery::mock(SourceAdapter::class);
        $adapter->shouldReceive('discover')->once()->andReturn([$candidate]);
        $registry = Mockery::mock(SourceRegistry::class);
        $registry->shouldReceive('adapter')->once()->with(RegulatorySource::Rbi)->andReturn($adapter);
        Http::fake(['https://example.test/*' => Http::response('', 503)]);

        (new RunSourcePoll(RegulatorySource::Rbi))->handle($registry, app(AcquireDocument::class));

        $this->assertDatabaseHas('poll_runs', [
            'source' => 'rbi',
            'status' => 'partial',
            'failed_count' => 1,
        ]);
        $this->assertStringContainsString(
            'acquisition_failed:RBI-BROKEN-1',
            (string) PollRun::query()->sole()->error,
        );
    }

    public function test_poll_counts_and_records_each_malformed_candidate_without_stopping(): void
    {
        $valid = new DocumentCandidate(
            RegulatorySource::Rbi,
            'RBI-CIRC-2026-002',
            'Valid circular',
            'https://example.test/valid.pdf',
            'https://example.test/valid.pdf',
            publishedAt: CarbonImmutable::parse('2026-05-15'),
        );
        $invalid = [
            new DocumentCandidate(RegulatorySource::Rbi, '', 'Title', 'https://example.test/a.pdf', 'https://example.test/a.pdf', publishedAt: CarbonImmutable::parse('2026-05-15')),
            new DocumentCandidate(RegulatorySource::Rbi, '2', '', 'https://example.test/b.pdf', 'https://example.test/b.pdf', publishedAt: CarbonImmutable::parse('2026-05-15')),
            new DocumentCandidate(RegulatorySource::Rbi, '3', 'Title', 'https://example.test/c.pdf', null, publishedAt: CarbonImmutable::parse('2026-05-15')),
            new DocumentCandidate(RegulatorySource::Rbi, '4', 'Title', 'https://example.test/d.pdf', 'https://example.test/d.pdf'),
            new DocumentCandidate(RegulatorySource::Rbi, '5', 'Title', 'https://example.test/e.pdf', 'https://example.test/e.pdf', null, publishedAt: CarbonImmutable::parse('2026-05-15')),
            new DocumentCandidate(RegulatorySource::Rbi, '6', 'Title', '', 'https://example.test/f.pdf', publishedAt: CarbonImmutable::parse('2026-05-15')),
        ];
        $adapter = Mockery::mock(SourceAdapter::class);
        $adapter->shouldReceive('discover')->once()->andReturn([...$invalid, $valid]);
        $registry = Mockery::mock(SourceRegistry::class);
        $registry->shouldReceive('adapter')->once()->andReturn($adapter);
        Http::fake(['https://example.test/valid.pdf' => Http::response('pdf', 200, ['Content-Type' => 'application/pdf'])]);
        Queue::fake();

        (new RunSourcePoll(RegulatorySource::Rbi))->handle($registry, app(AcquireDocument::class));

        $run = PollRun::query()->sole();
        $this->assertSame(7, $run->discovered_count);
        $this->assertSame(1, $run->created_count);
        $this->assertSame(6, $run->failed_count);
        $this->assertSame('partial', $run->status);
        foreach (['source_document_id', 'title', 'source_url', 'published_date', 'document_type', 'download_handle'] as $field) {
            $this->assertStringContainsString("candidate_invalid:{$field}", (string) $run->error);
        }
        $this->assertDatabaseHas('regulatory_documents', ['source_document_id' => 'RBI-CIRC-2026-002']);
    }

    public function test_configured_feed_adapter_preserves_canonical_source_identity_and_contract_fields(): void
    {
        config(['sahkarai.ingestion.sources.rbi.feed_url' => 'https://rbi.example.test/feed.xml']);
        Http::fake(['https://rbi.example.test/feed.xml' => Http::response(<<<'XML'
            <?xml version="1.0"?>
            <rss><channel><item>
              <guid>RBI-CIRC-2026-001</guid>
              <title>Capital Adequacy Update</title>
              <link>https://rbi.example.test/RBI-CIRC-2026-001.pdf</link>
              <pubDate>Fri, 15 May 2026 09:00:00 GMT</pubDate>
            </item></channel></rss>
            XML)]);

        $candidates = iterator_to_array((new ConfiguredFeedAdapter(RegulatorySource::Rbi))->discover());

        $this->assertCount(1, $candidates);
        $candidate = $candidates[0];
        $this->assertSame('RBI-CIRC-2026-001', $candidate->sourceDocumentId);
        $this->assertSame('Capital Adequacy Update', $candidate->title);
        $this->assertSame('https://rbi.example.test/RBI-CIRC-2026-001.pdf', $candidate->sourceUrl);
        $this->assertSame('2026-05-15', $candidate->publishedAt?->toDateString());
        $this->assertSame(DocumentType::Other, $candidate->documentType);
        $this->assertNotSame('', $candidate->downloadUrl);
    }

    public function test_issue_report_records_exact_version_and_locale_with_optional_category(): void
    {
        [, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier1()->create();

        $this->actingAs($user)->post(route('interpretations.issues.store', $version->interpretation), [
            'category' => null,
            'locale' => 'hi',
            'description' => 'The Hindi wording should be reviewed.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('issue_reports', [
            'user_id' => $user->id,
            'interpretation_id' => $version->interpretation->id,
            'document_version_id' => $version->id,
            'locale' => 'hi',
            'category' => null,
            'details' => 'The Hindi wording should be reviewed.',
        ]);
    }

    public function test_revision_notification_explicitly_references_the_prior_version(): void
    {
        [$document, $prior] = $this->documentWithInterpretation();
        $revision = $document->versions()->create([
            'version' => 2,
            'supersedes_id' => $prior->id,
            'status' => 'published',
            'extraction_status' => 'ok',
            'interpretation_status' => 'published',
            'original_path' => 'originals/test-v2.txt',
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', (string) Str::uuid()),
            'extracted_text' => 'Revised source.',
            'acquired_at' => now(),
        ]);
        $user = User::factory()->tier1()->create();
        $user->subscription()->create([
            'tier' => Tier::Tier1,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);
        $user->notificationPreference()->create();
        DocumentView::create([
            'user_id' => $user->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $prior->id,
            'last_viewed_at' => now(),
        ]);

        app(NotifyRegulatoryUpdate::class)->handle($revision);

        $notification = $user->productNotifications()->sole();
        $this->assertSame($prior->id, $notification->data['supersedes_version_id']);
        $this->assertSame(1, $notification->data['supersedes_version']);
        $this->assertStringContainsString('version 1', $notification->body);
    }

    public function test_new_tier_two_cycle_expires_unused_credits_then_grants_full_allowance(): void
    {
        $user = User::factory()->tier2(175)->create();
        Subscription::create([
            'user_id' => $user->id,
            'provider_subscription_id' => 'sub_renewal',
            'tier' => Tier::Tier2,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now(),
        ]);
        $payload = [
            'event' => 'subscription.charged',
            'payload' => ['subscription' => ['entity' => [
                'id' => 'sub_renewal',
                'status' => 'active',
                'current_start' => now()->timestamp,
                'current_end' => now()->addMonth()->timestamp,
            ]]],
        ];

        app(ProcessRazorpayWebhook::class)->handle('renewal_evt_1', $payload);

        $this->assertSame(200, $user->refresh()->credits_balance);
        $this->assertDatabaseHas('credit_ledger', ['user_id' => $user->id, 'amount' => -175, 'reason' => 'adjustment']);
        $this->assertDatabaseHas('credit_ledger', ['user_id' => $user->id, 'amount' => 200, 'reason' => 'grant_cycle']);
    }

    public function test_context_restart_and_version_navigation_remain_pinned_to_the_selected_revision(): void
    {
        [$document, $prior] = $this->documentWithInterpretation();
        $latest = $document->versions()->create([
            'version' => 2,
            'supersedes_id' => $prior->id,
            'status' => 'published',
            'extraction_status' => 'ok',
            'interpretation_status' => 'published',
            'original_path' => 'originals/test-v2.txt',
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', (string) Str::uuid()),
            'extracted_text' => 'Latest source.',
            'acquired_at' => now(),
        ]);
        $user = User::factory()->tier2()->create();
        $chat = Chat::create([
            'user_id' => $user->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $prior->id,
            'status' => 'closed_context_full',
        ]);

        $this->actingAs($user)->get(route('archive.show', ['document' => $document, 'version' => $prior->id]))
            ->assertInertia(fn ($page) => $page->where('document.latest_version.id', $prior->id));
        $this->actingAs($user)->post(route('chats.restart', $chat))->assertRedirect();

        $fresh = Chat::query()->whereKeyNot($chat->id)->sole();
        $this->assertSame($prior->id, $fresh->document_version_id);
        $this->assertNotSame($latest->id, $fresh->document_version_id);
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_context_threshold_allows_equality_and_closes_only_when_projected_tokens_exceed_it(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier2(2)->create();
        $threshold = (int) config('sahkarai.ai.context_window_tokens');
        $chat = Chat::create([
            'user_id' => $user->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $version->id,
            'context_tokens' => $threshold - 1,
        ]);

        $this->assertNull(app(SendChatMessage::class)->prepare($user, $chat, 'a', (string) Str::uuid()));
        $this->assertSame('active', $chat->refresh()->status);
        $this->assertSame($threshold, $chat->context_tokens);

        try {
            app(SendChatMessage::class)->prepare($user->refresh(), $chat, 'b', (string) Str::uuid());
            $this->fail('Expected the projected context overflow to be rejected.');
        } catch (ValidationException) {
            $this->assertSame('closed_context_full', $chat->refresh()->status);
            $this->assertDatabaseCount('chat_messages', 1);
            $this->assertSame(1, $user->refresh()->credits_balance);
        }
    }

    public function test_zero_credit_message_is_rejected_with_stable_reason_and_rolls_back_the_message(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier2(0)->create();
        $chat = Chat::create([
            'user_id' => $user->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $version->id,
        ]);

        try {
            app(SendChatMessage::class)->prepare($user, $chat, 'Can I send this?', (string) Str::uuid());
            $this->fail('Expected zero-credit validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame('no_credits_remaining', $exception->errors()['credits'][0]);
            $this->assertDatabaseCount('chat_messages', 0);
            $this->assertDatabaseCount('credit_ledger', 0);
        }
    }

    public function test_admin_issue_triage_records_contract_status_note_actor_and_timestamp(): void
    {
        [, $version] = $this->documentWithInterpretation();
        $reporter = User::factory()->tier1()->create();
        $admin = User::factory()->admin()->create();
        $issue = $version->interpretation->issueReports()->create([
            'user_id' => $reporter->id,
            'document_version_id' => $version->id,
            'locale' => 'en',
            'category' => 'wrong_applicability',
            'details' => 'This tag needs review.',
        ]);

        $this->actingAs($admin)->patch(route('ops.issues.update', $issue), [
            'triage_status' => 'wontfix',
            'internal_note' => 'User misunderstood the applicability tag.',
        ])->assertSessionHasNoErrors();

        $issue->refresh();
        $this->assertSame('wontfix', $issue->status);
        $this->assertSame('User misunderstood the applicability tag.', $issue->internal_note);
        $this->assertSame($admin->id, $issue->triaged_by);
        $this->assertNotNull($issue->triaged_at);
        $this->assertNotNull($issue->resolved_at);
    }

    public function test_admin_user_lookup_returns_subscription_credits_chat_count_and_last_activity(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->tier2(137)->create(['email' => 'lookup@example.test']);
        $user->subscription()->create([
            'tier' => Tier::Tier2,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);
        Chat::create([
            'user_id' => $user->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $version->id,
        ]);

        $this->actingAs($admin)->get(route('ops.dashboard', ['q' => 'lookup@example.test']))
            ->assertInertia(fn ($page) => $page
                ->where('users.0.email', 'lookup@example.test')
                ->where('users.0.tier', 'tier_2')
                ->where('users.0.subscription_status', 'active')
                ->where('users.0.credits_balance', 137)
                ->where('users.0.chat_count', 1)
                ->has('users.0.last_activity'));
    }

    public function test_saas_admin_dashboard_entry_redirects_to_operations(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('ops.dashboard'));
    }

    public function test_expired_account_purge_removes_personal_data_and_anonymizes_retained_issue(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier2()->create(['credits_balance' => 10]);
        $chat = Chat::create([
            'user_id' => $user->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $version->id,
        ]);
        $message = $chat->messages()->create(['role' => 'user', 'content' => 'Private question', 'token_count' => 2]);
        $user->creditLedger()->create([
            'amount' => -1,
            'balance_after' => 9,
            'reason' => 'debit_message',
            'idempotency_key' => 'purge-test-debit',
            'subject_type' => $message->getMorphClass(),
            'subject_id' => $message->id,
        ]);
        $notification = ProductNotification::create([
            'user_id' => $user->id,
            'type' => 'regulatory_update',
            'title' => 'Private notification',
            'body' => 'Private body',
        ]);
        $issue = IssueReport::create([
            'user_id' => $user->id,
            'interpretation_id' => $version->interpretation->id,
            'document_version_id' => $version->id,
            'locale' => 'en',
            'category' => 'inaccurate',
            'details' => 'Retain this report without its reporter.',
        ]);
        $user->update(['hard_delete_at' => now()->subSecond()]);
        $user->delete();

        (new PurgeExpiredAccounts)->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('chats', ['id' => $chat->id]);
        $this->assertDatabaseMissing('chat_messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('credit_ledger', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('product_notifications', ['id' => $notification->id]);
        $this->assertDatabaseHas('issue_reports', ['id' => $issue->id, 'user_id' => null]);
    }

    public function test_tier_two_account_settings_expose_only_the_owners_credit_ledger(): void
    {
        $alice = User::factory()->tier2()->create(['credits_balance' => 9]);
        $bob = User::factory()->tier2()->create(['credits_balance' => 50]);
        $aliceEntry = $alice->creditLedger()->create([
            'amount' => -1,
            'balance_after' => 9,
            'reason' => 'debit_message',
            'idempotency_key' => 'alice-ledger-entry',
        ]);
        $bobEntry = $bob->creditLedger()->create([
            'amount' => 50,
            'balance_after' => 50,
            'reason' => 'adjustment',
            'idempotency_key' => 'bob-ledger-entry',
        ]);

        $this->actingAs($alice)->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->has('creditLedger', 1)
                ->where('creditLedger.0.id', $aliceEntry->id)
                ->where('creditLedger.0.amount', -1)
                ->where('creditLedger.0.balance_after', 9)
                ->where('creditLedger.0.reason', 'debit_message'));

        $this->assertNotSame($aliceEntry->id, $bobEntry->id);
    }

    public function test_notification_centre_read_actions_are_owner_scoped_and_read_all_is_complete(): void
    {
        $alice = User::factory()->tier1()->create();
        $bob = User::factory()->tier1()->create();
        $first = ProductNotification::create([
            'user_id' => $alice->id, 'type' => 'regulatory_update', 'title' => 'First', 'body' => 'First update.',
        ]);
        $second = ProductNotification::create([
            'user_id' => $alice->id, 'type' => 'regulatory_update', 'title' => 'Second', 'body' => 'Second update.',
        ]);
        $foreign = ProductNotification::create([
            'user_id' => $bob->id, 'type' => 'regulatory_update', 'title' => 'Private', 'body' => 'Bob update.',
        ]);

        $this->actingAs($alice)->patch(route('notifications.read', $foreign))->assertForbidden();
        $this->assertNull($foreign->refresh()->read_at);

        $this->actingAs($alice)->patch(route('notifications.read', $first))->assertRedirect();
        $this->assertNotNull($first->refresh()->read_at);
        $this->assertNull($second->refresh()->read_at);

        $this->actingAs($alice)->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertNotNull($second->refresh()->read_at);
        $this->assertNull($foreign->refresh()->read_at);
    }

    public function test_chat_exports_cover_all_formats_statuses_metadata_and_ownership(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $alice = User::factory()->tier2()->create();
        $bob = User::factory()->tier2()->create();
        $chat = Chat::create([
            'user_id' => $alice->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $version->id,
            'locale' => 'gu',
            'status' => 'closed_context_full',
            'closed_at' => now(),
        ]);
        $chat->messages()->create([
            'role' => 'assistant',
            'content' => 'The answer is grounded in the selected revision.',
            'metadata' => ['insight' => ['kind' => 'deadline', 'value' => '2026-08-01']],
        ]);

        $json = $this->actingAs($alice)->get(route('chats.export', [$chat, 'json']))
            ->assertOk()
            ->assertHeader('content-disposition', "attachment; filename=sahkarai-chat-{$chat->id}.json")
            ->assertJsonPath('chat.status', 'closed_context_full')
            ->assertJsonPath('chat.document_version_id', $version->id)
            ->assertJsonPath('document_version.source_document_id', $document->source_document_id)
            ->assertJsonPath('messages.0.content', 'The answer is grounded in the selected revision.')
            ->assertJsonPath('insights.0.kind', 'deadline');
        $this->assertSame('gu', $json->json('chat.locale'));

        $this->actingAs($alice)->get(route('chats.export', [$chat, 'md']))
            ->assertOk()->assertHeader('content-type', 'text/markdown; charset=UTF-8')
            ->assertSee('The answer is grounded in the selected revision.');
        $pdf = $this->actingAs($alice)->get(route('chats.export', [$chat, 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $this->actingAs($bob)->get(route('chats.export', [$chat, 'json']))->assertForbidden();
        $this->actingAs($alice)->get(route('chats.export', [$chat, 'xml']))->assertUnprocessable();
    }

    public function test_deferred_downgrade_can_be_resumed_or_applied_at_the_anniversary(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        $user = User::factory()->tier2(150)->create();
        $subscription = $user->subscription()->create([
            'provider_subscription_id' => 'sub_deferred',
            'tier' => Tier::Tier2,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => Carbon::parse('2026-05-01'),
            'current_period_end' => Carbon::parse('2026-06-01'),
        ]);
        $gateway = Mockery::mock(BillingGateway::class);
        $gateway->expects('changePlan')->withArgs(fn ($actual, $tier, $atCycleEnd) => $actual->is($subscription)
            && $tier === Tier::Tier1 && $atCycleEnd)->andReturn(['id' => 'sub_deferred', 'scheduled' => true]);
        $this->app->instance(BillingGateway::class, $gateway);

        $this->actingAs($user)->post(route('billing.subscribe'), ['tier' => 'tier_1'])->assertSessionHasNoErrors();
        $this->assertSame(Tier::Tier2, $user->refresh()->tier);
        $this->assertSame(150, $user->credits_balance);
        $this->assertSame(Tier::Tier1, $subscription->refresh()->pending_tier);
        $this->assertTrue($subscription->cancel_at->equalTo(Carbon::parse('2026-06-01')));

        $resumeGateway = Mockery::mock(BillingGateway::class);
        $resumeGateway->expects('changePlan')->withArgs(fn ($actual, $tier, $atCycleEnd) => $actual->is($subscription)
            && $tier === Tier::Tier2 && ! $atCycleEnd)->andReturn(['id' => 'sub_deferred', 'scheduled' => false]);
        $this->app->instance(BillingGateway::class, $resumeGateway);
        $this->actingAs($user)->post(route('billing.resume'))->assertSessionHasNoErrors();
        $this->assertNull($subscription->refresh()->pending_tier);

        $subscription->update(['pending_tier' => Tier::Tier1, 'cancel_at' => Carbon::parse('2026-06-01')]);
        Carbon::setTestNow('2026-06-01 00:00:00');
        Notification::fake();
        (new ApplyPendingSubscriptionChanges)->handle();

        $this->assertSame(Tier::Tier1, $user->refresh()->tier);
        $this->assertSame(0, $user->credits_balance);
        $this->assertSame(Tier::Tier1, $subscription->refresh()->tier);
        $this->assertNull($subscription->pending_tier);
        $this->assertDatabaseHas('product_notifications', ['user_id' => $user->id, 'type' => 'billing_downgraded']);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'channel' => 'in_app', 'status' => 'delivered']);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'channel' => 'email', 'status' => 'queued']);
    }

    public function test_reconciliation_records_provider_drift_and_exposes_an_ops_alert(): void
    {
        $user = User::factory()->tier1()->create();
        $subscription = $user->subscription()->create([
            'provider_subscription_id' => 'sub_drift',
            'tier' => Tier::Tier1,
            'status' => SubscriptionStatus::Cancelled,
        ]);
        $gateway = Mockery::mock(BillingGateway::class);
        $gateway->expects('fetchSubscription')->withArgs(fn ($actual) => $actual->is($subscription))
            ->andReturn(['id' => 'sub_drift', 'status' => 'active']);

        (new ReconcileSubscriptions)->handle($gateway);

        $drift = ReconciliationDrift::sole();
        $this->assertSame('cancelled', $drift->local_value);
        $this->assertSame('active', $drift->provider_value);
        $this->assertDatabaseHas('ops_alerts', ['type' => 'subscription_drift', 'severity' => 'warning']);
    }

    public function test_signed_restore_works_only_inside_the_deletion_window(): void
    {
        $user = User::factory()->create(['hard_delete_at' => now()->addDays(30)]);
        $user->delete();
        $restoreUrl = URL::temporarySignedRoute('account.restore', now()->addHour(), ['user' => $user->id]);

        $this->get($restoreUrl)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->refresh());
        $this->assertNull($user->deleted_at);
        $this->assertNull($user->hard_delete_at);

        auth()->logout();
        $expired = User::factory()->create(['hard_delete_at' => now()->subSecond()]);
        $expired->delete();
        $expiredUrl = URL::temporarySignedRoute('account.restore', now()->addHour(), ['user' => $expired->id]);
        $this->get($expiredUrl)->assertGone();
    }

    public function test_chat_messages_and_credit_ledger_entries_are_immutable(): void
    {
        [$document, $version] = $this->documentWithInterpretation();
        $user = User::factory()->tier2()->create();
        $chat = Chat::create([
            'user_id' => $user->id,
            'regulatory_document_id' => $document->id,
            'document_version_id' => $version->id,
        ]);
        $message = ChatMessage::create(['chat_id' => $chat->id, 'role' => 'user', 'content' => 'Original']);
        $entry = CreditLedger::create([
            'user_id' => $user->id, 'amount' => -1, 'balance_after' => 199,
            'reason' => 'debit_message', 'idempotency_key' => 'immutable-ledger',
        ]);

        try {
            $message->update(['content' => 'Tampered']);
            $this->fail('Expected a chat message update to be rejected.');
        } catch (\LogicException $exception) {
            $this->assertSame('Chat messages are immutable.', $exception->getMessage());
        }
        try {
            $entry->delete();
            $this->fail('Expected a ledger delete to be rejected.');
        } catch (\LogicException $exception) {
            $this->assertSame('Credit ledger entries are immutable.', $exception->getMessage());
        }
        $this->assertSame('Original', $message->fresh()->content);
        $this->assertNotNull($entry->fresh());
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
            'version' => 1, 'status' => 'published', 'extraction_status' => 'ok', 'interpretation_status' => 'published',
            'original_path' => 'originals/test.txt',
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
