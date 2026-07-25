<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulatory_documents', function (Blueprint $table): void {
            $table->foreignId('ingested_by_user_id')
                ->nullable()
                ->after('uploaded_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reference_number')->nullable()->after('source_document_id');
            $table->jsonb('manual_metadata_fields')->default('[]')->after('upload_description');
            $table->boolean('is_public')->default(true)->after('manual_metadata_fields')->index();
            $table->index(['ingested_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('regulatory_documents', function (Blueprint $table): void {
            $table->dropIndex(['ingested_by_user_id', 'created_at']);
            $table->dropConstrainedForeignId('ingested_by_user_id');
            $table->dropColumn(['reference_number', 'manual_metadata_fields', 'is_public']);
        });
    }
};
