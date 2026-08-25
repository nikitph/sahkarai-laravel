<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table): void {
            $table->string('video_status')->default('not_requested')->index();
        });

        Schema::create('explainer_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('storage_disk')->nullable();
            $table->string('video_path')->nullable();
            $table->string('manifest_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->char('input_hash', 64)->nullable()->index();
            $table->string('pipeline_version')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explainer_videos');
        Schema::table('document_versions', fn (Blueprint $table) => $table->dropColumn('video_status'));
    }
};
