<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulatory_documents', function (Blueprint $table): void {
            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->after('source_url')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('upload_description')->nullable()->after('uploaded_by_user_id');
            $table->index(['uploaded_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('regulatory_documents', function (Blueprint $table): void {
            $table->dropIndex(['uploaded_by_user_id', 'created_at']);
            $table->dropConstrainedForeignId('uploaded_by_user_id');
            $table->dropColumn('upload_description');
        });
    }
};
