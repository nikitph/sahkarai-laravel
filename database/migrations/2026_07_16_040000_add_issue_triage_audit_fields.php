<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_reports', function (Blueprint $table): void {
            $table->text('internal_note')->nullable()->after('details');
            $table->foreignId('triaged_by')->nullable()->after('internal_note')->constrained('users')->nullOnDelete();
            $table->timestamp('triaged_at')->nullable()->after('triaged_by');
        });
    }

    public function down(): void
    {
        Schema::table('issue_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('triaged_by');
            $table->dropColumn(['internal_note', 'triaged_at']);
        });
    }
};
