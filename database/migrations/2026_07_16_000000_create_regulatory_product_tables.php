<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('individual_member')->index();
            $table->string('tier')->default('free')->index();
            $table->string('locale', 5)->default('en');
            $table->unsignedInteger('credits_balance')->default(0);
            $table->timestamp('credits_reset_at')->nullable();
            $table->timestamp('hard_delete_at')->nullable()->index();
            $table->softDeletes();
        });

        Schema::create('poll_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source')->index();
            $table->string('kind')->default('scheduled');
            $table->string('status')->default('running')->index();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('discovered_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('regulatory_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('source')->index();
            $table->string('source_document_id');
            $table->string('title');
            $table->string('document_type')->default('other')->index();
            $table->string('applicability')->default('generic')->index();
            $table->jsonb('applicability_tags')->default('[]');
            $table->date('published_at')->nullable()->index();
            $table->date('effective_at')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_backfill')->default(false);
            $table->timestamps();
            $table->unique(['source', 'source_document_id']);
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('regulatory_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('document_versions')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('acquired')->index();
            $table->string('original_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->index();
            $table->longText('extracted_text')->nullable();
            $table->text('extraction_error')->nullable();
            $table->timestamp('acquired_at');
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();
            $table->unique(['regulatory_document_id', 'version']);
            $table->unique(['regulatory_document_id', 'sha256']);
        });

        Schema::create('interpretations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->jsonb('locale_payloads')->nullable();
            $table->jsonb('failed_locales')->nullable();
            $table->jsonb('locale_attempts')->nullable();
            $table->jsonb('deadlines')->nullable();
            $table->string('model_id')->nullable();
            $table->string('prompt_version')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('terminal_error')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('regulatory_document_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_viewed_at');
            $table->timestamps();
            $table->unique(['user_id', 'regulatory_document_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('razorpay');
            $table->string('provider_customer_id')->nullable()->index();
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('tier')->default('free');
            $table->string('status')->default('free')->index();
            $table->string('pending_tier')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->jsonb('provider_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('reason')->index();
            $table->string('idempotency_key')->unique();
            $table->nullableMorphs('subject');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('chats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('regulatory_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('context_tokens')->default(0);
            $table->timestamp('context_closed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->string('model_id')->nullable();
            $table->string('request_id')->nullable()->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['chat_id', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('source_rbi')->default(true);
            $table->boolean('source_income_tax')->default(true);
            $table->boolean('source_gst')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->string('source_rbi_cadence')->default('daily_digest');
            $table->string('source_income_tax_cadence')->default('daily_digest');
            $table->string('source_gst_cadence')->default('daily_digest');
            $table->timestamps();
        });

        Schema::create('product_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dedupe_key')->nullable()->unique();
            $table->string('type')->index();
            $table->string('title');
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('product_notification_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('status')->default('pending')->index();
            $table->string('locale', 5)->default('en');
            $table->string('provider_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->foreign('product_notification_id')->references('id')->on('product_notifications')->nullOnDelete();
        });

        Schema::create('issue_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('interpretation_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('open')->index();
            $table->string('category')->default('other');
            $table->text('details');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('processed_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('razorpay');
            $table->string('provider_event_id');
            $table->string('event_type')->index();
            $table->char('payload_hash', 64);
            $table->jsonb('payload');
            $table->string('status')->default('processing');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('ops_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->index();
            $table->string('severity')->default('warning')->index();
            $table->string('title');
            $table->text('details')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_drifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->text('local_value')->nullable();
            $table->text('provider_value')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_drifts');
        Schema::dropIfExists('ops_alerts');
        Schema::dropIfExists('processed_webhooks');
        Schema::dropIfExists('issue_reports');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('product_notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chats');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('document_views');
        Schema::dropIfExists('interpretations');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('regulatory_documents');
        Schema::dropIfExists('poll_runs');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'role', 'tier', 'locale', 'credits_balance', 'credits_reset_at',
                'hard_delete_at', 'deleted_at',
            ]);
        });
    }
};
