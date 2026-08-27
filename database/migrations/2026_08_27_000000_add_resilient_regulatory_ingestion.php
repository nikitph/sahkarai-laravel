<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table): void {
            $table->string('extraction_method')->nullable()->after('extraction_status')->index();
            $table->char('extracted_text_sha256', 64)->nullable()->after('extracted_path');
            $table->timestamp('needs_review_at')->nullable()->after('extracted_at')->index();
        });

        Schema::create('extraction_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->string('method')->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('status')->index();
            $table->unsignedInteger('attempt');
            $table->string('request_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['document_version_id', 'method', 'attempt']);
        });

        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->boolean('source_cbic')->default(true)->after('source_gst');
            $table->boolean('source_nabard')->default(true)->after('source_cbic');
            $table->string('source_cbic_cadence')->default('daily_digest')->after('source_gst_cadence');
            $table->string('source_nabard_cadence')->default('daily_digest')->after('source_cbic_cadence');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'source_cbic',
                'source_nabard',
                'source_cbic_cadence',
                'source_nabard_cadence',
            ]);
        });

        Schema::dropIfExists('extraction_attempts');

        Schema::table('document_versions', function (Blueprint $table): void {
            $table->dropColumn(['extraction_method', 'extracted_text_sha256', 'needs_review_at']);
        });
    }
};
